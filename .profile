# Only the folders Mikhmon writes to need to be writable by the web server:
#   include/  config.php, theme.php, lang.php, quickbt.php, secret.key.php
#   voucher/  voucher templates and temp.php
#   img/      uploaded logos
chmod -R u+rwX,go+rX,go-w .
chmod -R ug+rwX include voucher img
