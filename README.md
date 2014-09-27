izzum
=====

izzum php library


'Yo man, who gots the izzum for tonights festivities?'



Installation / Usage
--------------------

Define the following requirement in your composer.json file:
``` json
{
    "require": {
        "izzum/izzum": "*"
    }
}
```

Autoloading is taken care of by Composer. You just have to include the composer autoload file in your project:
``` php
<?php
// bootstrap.php
// Include Composer Autoload (relative to project root).
require_once "vendor/autoload.php";
```

To create a basic statemachine using izzum:
``` php
<?php
// bootstrap.php
require_once "vendor/autoload.php";

use izzum\command\Command;

....
```


contributors:
- Richard Ruiter
- Romuald Villetet
