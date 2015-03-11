# Baun-Admin

An Admin UI for Baun.

## Install

Add `bauncms/baun-admin` to your composer.json or run:

    composer require bauncms/baun-admin

Add this line to your `config/plugins.php`:

    'BaunPlugin\Admin\Admin'

Use the Baun CLI to publish the config files and assets:

    php baun publish:config bauncms/baun-admin
    php baun publish:assets bauncms/baun-admin
