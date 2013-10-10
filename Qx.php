<?php

/**
 * Qx MicroFramework for Php Applications
 *
 * @author Brice Dauzats
 */
require_once 'qx/Locale.php';
require_once 'qx/Exception.php';
require_once 'qx/Observable.php';
require_once 'qx/Loader.php';


require_once 'qx/Response.php';
require_once 'qx/ResponsePart.php';
require_once 'qx/Controller.php';
require_once 'qx/ViewController.php';
require_once 'qx/App.php';

require_once 'qx/db/ObjectModel.php';

require_once 'qx/Data.php';
require_once 'qx/Session.php';
require_once 'qx/View.php';
require_once 'qx/Request.php';
require_once 'qx/Route.php';
require_once 'qx/Routes.php';

require_once 'qx/Config.php';

require_once 'qx/Url.php';
require_once 'qx/Tools.php';
require_once 'qx/Debug.php';

class Qx {
    
}

\qx\Loader::AddPath('lib'); //Add lib
\qx\Loader::AddPath(__DIR__); //Add current dir