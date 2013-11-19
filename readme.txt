=== WP-Vindi ===
Contributors: agoulart, dantasrodrigo
Website Link: http://vindi.com.br
Tags: Vindi, subscription, registration, tools, membership, pagamento-recorrente, cobranca-recorrente, cobrança-recorrente, recurring, site-de-assiantura, assinaturas, faturamento-recorrente, recorrencia, assinatura
Requires at least: 2.9
Tested up to: WordPress 3.7.1
Stable Tag: 1.0.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html


WP-Vindi allows users to integrate the Vindi service with WordPress.

== Description ==

WP-Vindi allows users to integrate the Vindi service with WordPress. Giving you the ability to run a membership or subscription-based website, controlling access to content based on paid or free subscriptions.

== Installation ==

1. Create a WordPress page where people will sign up for access to your site. This is where a user will be taken to register and begin the signup process.
  a. Place the shortcode [vindi] in the body of your page somewhere to create the sign up form.
  b. You can fill the rest of the page in with whatever else you want, any content that makes sense for you.
  c. Note the URL of this page (full URL)
2. Create a WordPress page where your users will return to after a successful signup.
  a. This will be after they complete a successful transaction
  b. This will be useful for tracking analytics. You can use Google Analytics to make this the Goal page, and track signups and conversions as goals.
  c. You should also consider using this page to give information to your users once they've finished paying for their memberships, such as welcome to our site, here's a link to our FAQ. Here are some pages you may want to check out.
  d. The user's login information will come to them in an email. It is helpful to tell them to check their email to get that information and allow them to login.
  e. This email is the typical New User email, and you can modify it via other plugins.
3. Got to the Vindi settings section of the WordPress admin area.
  a. Add your API Key (you get this from Vindi when logged in)
  b. Your Domain is the subdomain you get when creating a new site inside of the Vindi system
  c. Mode, leave it Test until you're ready to make it live.
  d. Sign-up Type should be left at Default unless you know what you're doing.
  e. Signup Link - Place the URL to the page you created in step 1.
4. Go to Vindi.com.br and login. 
5. Create your plans at Vindi.com You'll create a group and plans below that. (these will be the different access and plan levels). Feel free to just have one plan - standard access.
6. While creating your plan, add the following line to your return parameters: subscription_id={subscription_id}&customer_reference={customer_reference}
  a. This is CRITICAL. DO NOT SKIP THIS STEP!!! It is the information that is passed after a successful transaction that tells WordPress to create the new account.
7. Now that you have a product identified, your WordPress account will use that information and allow you to set pages or posts to private. Go create a test post and look for the Vindi settings within that post edit page, and check that it's for members only (or whatever you named your product).
8. Now, when you try and access that product, you'll be told you have to be logged in to view, and it should give you a link to sign up for an account.
9. Logout of WordPress and try it. Go through every step and make sure it's working before your turn off the Test function.
10. If that works, you'll need to continue setting up your Vindi account, inputting whatever information you need for your merchant account, payment gateway. See vindi's support for more information on that.

== Frequently Asked Questions ==

For help setting up and configuring Vindi - Assinaturas e Cobrança Recorrente please refer to our [user guide](http://vindi.com.br/blog/plugin)

== Changelog ==

= 1.0.0 =
* Based on Chargify plugin

