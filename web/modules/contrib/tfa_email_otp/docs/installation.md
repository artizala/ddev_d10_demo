# Installation

## Prerequisites
- Drupal 10 or 11  
- Two‑Factor Authentication (`tfa`) module

## Steps
1. Install via Composer:
   ```bash
   composer require 'drupal/tfa_email_otp'
   ```
2. Enable module:
   ```bash
   drush en tfa_email_otp
   drush cache:rebuild
   ```
3. Configure via **Configuration → People → Two‑factor Authentication**: select **Email OTP** as default validation plugin, save. It comes with an email template in which you can customize the OTP email sent to the user.
