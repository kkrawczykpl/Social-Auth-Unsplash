# Social Auth Unsplash

Social Auth Unsplash allows users to register and login to your Drupal site with their Unsplash account. The module allows websites to request any scopes, so any tasks requiring authentication with Unsplash services can be performed. This module is based on Social Auth and Social API projects.

This module adds a path user/login/unsplash which redirects the user to Unsplash Accounts for authentication.

After Unsplash API has returned the user to your site, the module compares the email address provided by Unsplash. If your site already has an account that has previously registered using Unsplash, user is logged in. If not, a new user account is created. Also, a Unsplash account can be associated to an authenticated user.

Login process can be initiated from the "Unsplash" button in the Social Auth block. Alternatively, site builders can place (and theme) a link to user/login/unsplash wherever on the site.