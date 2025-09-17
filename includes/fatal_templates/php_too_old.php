<?php
$MIN_PHP_VER = "8.0";
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Polymerize startup error</title>
    </head>
    <body>
        <h1>Your version of PHP is too old!</h1>
        <p>
            Polymerize requires at least <b>PHP  <?= $MIN_PHP_VER ?></b>. Your server is running
            <b>PHP <?= PHP_VERSION ?></b>.
        </p>
        <p>
            Please upgrade and try again.
        </p>
    </body>
</html>