<?php

namespace Drupal\social_auth_unsplash\Controller;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\SocialAuthDataHandler;
use Drupal\social_auth\SocialAuthUserManager;
use Drupal\social_auth_unsplash\UnsplashAuthManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for Simple Unsplash Connect module routes.
 */
class UnsplashAuthController extends ControllerBase {
  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The network plugin manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  private $networkManager;

  /**
   * The user manager.
   *
   * @var \Drupal\social_auth\SocialAuthUserManager
   */
  private $userManager;

  /**
   * The Unsplash authentication manager.
   *
   * @var \Drupal\social_auth_unsplash\UnsplashAuthManager
   */
  private $unsplashManager;

  /**
   * Used to access GET parameters.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $request;

  /**
   * The Social Auth Data Handler.
   *
   * @var \Drupal\social_auth\SocialAuthDataHandler
   */
  private $dataHandler;

  /**
   * UnsplashAuthController constructor.
   *
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Used to get an instance of social_auth_unsplash network plugin.
   * @param \Drupal\social_auth\SocialAuthUserManager $user_manager
   *   Manages user login/registration.
   * @param \Drupal\social_auth_unsplash\UnsplashAuthManager $unsplash_manager
   *   Used to manage authentication methods.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   Used to access GET parameters.
   * @param \Drupal\social_auth\SocialAuthDataHandler $data_handler
   *   SocialAuthDataHandler object.
   */
  public function __construct(MessengerInterface $messenger,
                              NetworkManager $network_manager,
                              SocialAuthUserManager $user_manager,
                              UnsplashAuthManager $unsplash_manager,
                              RequestStack $request,
                              SocialAuthDataHandler $data_handler) {

    $this->messenger = $messenger;
    $this->networkManager = $network_manager;
    $this->userManager = $user_manager;
    $this->unsplashManager = $unsplash_manager;
    $this->request = $request;
    $this->dataHandler = $data_handler;

    // Sets the plugin id.
    $this->userManager->setPluginId('social_auth_unsplash');

    // Sets the session keys to nullify if user could not logged in.
    $this->userManager->setSessionKeysToNullify(['access_token', 'oauth2state']);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('plugin.network.manager'),
      $container->get('social_auth.user_manager'),
      $container->get('social_auth_unsplash.manager'),
      $container->get('request_stack'),
      $container->get('social_auth.data_handler')
    );
  }

  /**
   * Response for path 'user/login/unsplash'.
   */
  public function redirectToUnsplash() {
    $unsplash = $this->networkManager->createInstance('social_auth_unsplash')->getSdk();

    if (!$unsplash) {
      $this->messenger->addError('Social Auth Unsplash not configured properly. Contact site administrator.');
      return $this->redirect('user.login');
    }

    $this->unsplashManager->setClient($unsplash);

    $unsplash_login_url = $this->unsplashManager->getAuthorizationUrl();

    $state = $this->unsplashManager->getState();

    $this->dataHandler->set('oauth2state', $state);

    return new TrustedRedirectResponse($unsplash_login_url);
  }

  /**
   * Response for path 'user/login/unsplash/callback'.
   *
   * Unsplash returns the user here after user has authenticated in Unsplash.
   */
  public function callback() {
    $unsplash = $this->networkManager->createInstance('social_auth_unsplash')->getSdk();

    if (!$unsplash) {
      $this->messenger->addError('Social Auth Unsplash not configured properly. Contact site administrator.');
      return $this->redirect('user.login');
    }

    $state = $this->dataHandler->get('oauth2state');

    $retrievedState = $this->request->getCurrentRequest()->query->get('state');
    if (empty($retrievedState) || ($retrievedState !== $state)) {
      $this->userManager->nullifySessionKeys();
      $this->messenger->addError('Unsplash login failed. Unvalid OAuth2 State.');
      return $this->redirect('user.login');
    }

    $this->dataHandler->set('access_token', $this->unsplashManager->getAccessToken());

    $this->unsplashManager->setClient($unsplash)->authenticate();

    if (!$profile = $this->unsplashManager->getUserInfo()) {
      $this->messenger->addError('Unsplash login failed, could not load Unsplash profile. Contact site administrator.');
      return $this->redirect('user.login');
    }

    $data = $this->userManager->checkIfUserExists($profile->getId()) ? NULL : $this->unsplashManager->getExtraDetails();

    return $this->userManager->authenticateUser($profile->getUsername(), $profile->toArray()['email'], $profile->getId(), $this->unsplashManager->getAccessToken(), $profile->toArray()['profile_image']['medium'], $data);
  }

}
