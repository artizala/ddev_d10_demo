# TFA Email OTP Plugin

This module provides a TFA plugin to send an One-Time password (code) via email.
Which is required by [Create "Email one-time-code" Validation Plugin & related Setup Plugin](https://www.drupal.org/project/tfa/issues/2930541)


## Table of contents

- Requirements
- Installation
- Configuration


## Requirements

It is a TFA plugin which means, it requires [TFA module](https://www.drupal.org/project/tfa) and its dependencies installed.

## Installation

Install as you would normally install a contributed Drupal module. For further information, see [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

Like other TFA validation plugin, the configuration page is the TFA setting page(/admin/config/people/tfa). 

It comes with an email template in which you can customize the OTP email sent to the user.
