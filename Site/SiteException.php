<?php
 /**
 * @see Class_Exception
 */
namespace Wbengine\Site;

use Wbengine\Application\ApplicationException;

class SiteException extends ApplicationException {
    
    const ERROR_CONFIG_DOES_NOT_EXIST = 1001;
    const ERROR_NOT_INSTANCE_OF_SESSION = 1002;
    const ERROR_VALUE_KEY_IS_EMPTY = 1003;
    const ERROR_NO_SECTIONS = 1004;
    const ERROR_NO_TEMPLATE_NAME = 1005;


}
