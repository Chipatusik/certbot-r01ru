<?php

class ClientException extends Exception
{
    private $client;

    public function __construct($message, Client $client = null, Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->client = $client;
        if ($this->client instanceof Client && $this->client::$_is_login) {
            $this->client::logout();
        }
    }
}

function exception_handler($exception)
{
    echo $exception->getMessage();
}

set_exception_handler('exception_handler');

class Client
{
    protected static $_instance;
    protected static $_record_key_name = '_acme-challenge';
    protected static $_domain = '';
    protected static $_sdomain = '';
    protected static $_is_login = false;
    private $client;

    public static function getInstance()
    {
        $args = func_get_args();
        if (self::$_instance === null) {
            self::$_instance = new self($args);
        }
        if (!self::$_is_login) {
            self::$_instance::login($args[2], $args[3]);
        }
        return self::$_instance;
    }

    private function __construct($args)
    {
        $_edomain = explode('.', $args[1]);
        self::$_domain = implode('.', array_slice($_edomain, -2));
        self::$_sdomain = implode('.', array_slice($_edomain, 0, (count($_edomain) - 2)));

        if (self::$_sdomain != '') {
            Client::setRecordKeyName(Client::getRecordKeyName() . '.' . self::$_sdomain);
        }

        try {
            $this->client = new SoapClient(null, array
                (
                    'location' => $args[0],
                    'uri' => 'urn:RegbaseSoapInterface',
                    'exceptions' => true,
                    'user_agent' => 'RegbaseSoapInterfaceClient',
                    'trace' => 1
                )
            );
        } catch (SoapFault $fault) {
            throw new ClientException(PHP_EOL . "Couldn`t connect to SOAP server" . PHP_EOL . "Fault code: " . $fault->faultcode . PHP_EOL . "Fault message: " . $fault->faultstring . PHP_EOL);
        }
    }

    private function __clone()
    {
    }

    private function __wakeup()
    {
    }

    private static function getAddRecord($_data, $_info = '')
    {
        $_record_key_name = self::getRecordKeyName();
        return array(
            'owner' => $_record_key_name,
            'pri' => 0,
            'weight' => 0,
            'port' => 0,
            'data' => $_data,
            'sshfp_algorithm' => 0,
            'sshfp_type' => 0,
            'info' => $_info
        );
    }

    public static function setRecordKeyName($val)
    {
        return self::$_record_key_name = $val;
    }

    public static function getRecordKeyName()
    {
        return self::$_record_key_name;
    }

    public static function getRecords($domain = false, $type_record = 'TXT', $output = true)
    {
        $domain = $domain ? $domain : self::$_instance::$_domain;
        try {
            self::$_instance->getrrecords = self::$_instance->client->getRrRecords($domain);
        } catch (SoapFault $fault) {
            throw new ClientException(PHP_EOL . "Couldnt execute getRrRecords." . PHP_EOL . "Fault code: " . $fault->faultcode . PHP_EOL . "Fault message: " . $fault->faultstring . PHP_EOL, self::$_instance);
        }
        if (self::$_instance->getrrecords->status->code != '1') {
            throw new ClientException(PHP_EOL . "Failed to get RR records" . PHP_EOL . "Code: " . self::$_instance->getrrecords->status->code . ' Message: ' . self::$_instance->getrrecords->status->message . PHP_EOL, self::$_instance);
        } else {
            $_grecords = array();
            foreach (self::$_instance->getrrecords->data as $key => $record) {
                if ($record->type_record != $type_record) continue;
                $_grecords [] = $record;
                if ($output) {
                    $_str = implode('|', (array)$record);
                    echo $_str, PHP_EOL;
                }
                /*
                    $record->id
                    $record->owner
                    $record->type_record
                    $record->pri
                    $record->weight
                    $record->port
                    $record->data
                    $record->sshfp_algorithm
                    $record->sshfp_type
                    $record->info
                */
            }
            if (count($_grecords) == 0 && $output) {
                echo 'RR Records not found', PHP_EOL;
            }
        }

        return self::$_instance->getrrecords;
    }

    public static function getRecordByKeyName($key, $domain = false, $type_record = 'TXT', $output = true)
    {
        $domain = $domain ? $domain : self::$_instance::$_domain;
        $_records = self::$_instance::getRecords($domain, $type_record, false);
        $_keys = array();
        $_grecords = array();
        foreach ($_records->data as $k => $record) {
            if ($record->owner != $key && !in_array($record->id, $_keys)) continue;
            $_keys[] = $record->id;
            $_grecords [] = $record;
            if ($output) {
                $_str = implode('|', (array)$record);
                echo $_str, PHP_EOL;
            }
        }
        if (count($_grecords) == 0 && $output) {
            echo 'RR Record not found', PHP_EOL;
        }
        return $_grecords;
    }

    public static function addRecord($token, $domain = false, $type_record = 'TXT')
    {
        $domain = $domain ? $domain : self::$_instance::$_domain;
        try {
            self::$_instance->addnewrecord = self::$_instance->client->addNewRrRecord($domain, $type_record, self::getAddRecord($token));
        } catch (SoapFault $fault) {
            throw new ClientException(PHP_EOL . "Couldnt execute addNewRrRecord." . PHP_EOL . "Fault code: " . $fault->faultcode . PHP_EOL . "Fault message: " . $fault->faultstring . PHP_EOL, self::$_instance);
        }
        if (self::$_instance->addnewrecord->status->code != '1') {
            throw new ClientException(PHP_EOL . "Failed to add new RR record" . PHP_EOL . "Code: " . self::$_instance->addnewrecord->status->code . ' Message: ' . self::$_instance->addnewrecord->status->message . PHP_EOL, self::$_instance);
        } else {
            echo "addNewRrRecord status: " . self::$_instance->addnewrecord->status->name . PHP_EOL;
            echo "addNewRrRecord message: " . self::$_instance->addnewrecord->status->message . PHP_EOL;
        }

        return self::$_instance->addnewrecord;
    }

    public static function deleteRecord($id)
    {
        try {
            self::$_instance->deleterecord = self::$_instance->client->deleteRrRecord($id);
        } catch (SoapFault $fault) {
            throw new ClientException(PHP_EOL . "Couldnt execute deleteRrRecord." . PHP_EOL . "Fault code: " . $fault->faultcode . PHP_EOL . "Fault message: " . $fault->faultstring . PHP_EOL, self::$_instance);
        }
        if (self::$_instance->deleterecord->status->code != '1') {
            throw new ClientException(PHP_EOL . "Failed to delete RR record" . PHP_EOL . "Code: " . self::$_instance->deleterecord->status->code . ' Message: ' . self::$_instance->deleterecord->status->message . PHP_EOL, self::$_instance);
        } else {
            echo "deleteRrRecord status: " . self::$_instance->deleterecord->status->name . PHP_EOL;
            echo "deleteRrRecord message: " . self::$_instance->deleterecord->status->message . PHP_EOL;
        }
        return self::$_instance->deleterecord;
    }

    public static function deleteRecordByKeyName($key, $force = false, $domain = false, $type_record = 'TXT')
    {
        $domain = $domain ? $domain : self::$_instance::$_domain;
        $_records = self::$_instance::getRecordByKeyName($key, $domain, $type_record, false);

        if (count($_records) == 0) {
            echo 'RR Record not found', PHP_EOL;
        } else if (count($_records) > 1 && !$force) {
            throw new ClientException(PHP_EOL . "Failed to delete RR record" . PHP_EOL . "Message: multiple results, please clean up your dns" . PHP_EOL, self::$_instance);
        } else if (count($_records) > 1 && $force) {
            foreach ($_records as $k => $record) {
                self::$_instance::deleteRecord($record->id);
            }
        } else {
            self::$_instance::deleteRecord($_records[0]->id);
        }
    }

    public static function login($login, $password)
    {
        try {
            self::$_instance->loginresult = self::$_instance->client->logIn($login, $password);
        } catch (SoapFault $fault) {
            throw new ClientException(PHP_EOL . "Can`t log in." . PHP_EOL . "Fault code: " . $fault->faultcode . PHP_EOL . "Fault message: " . $fault->faultstring . PHP_EOL);
        }
        if (self::$_instance->loginresult->status->code == '0') {
            throw new ClientException(PHP_EOL . "Invalid login/password" . PHP_EOL . "Code: " . self::$_instance->loginresult->status->code . ' Message: ' . self::$_instance->loginresult->status->message . PHP_EOL);
        } else {
            self::$_instance->client->__setCookie('SOAPClient', self::$_instance->loginresult->status->message);
        }
        self::$_instance::$_is_login = true;
        echo "Successfully logged in as " . $login . PHP_EOL;
        return self::$_instance->loginresult;
    }

    public static function logout()
    {
        try {
            $logoutresult = self::$_instance->client->logOut();
        } catch (SoapFault $fault) {
            throw new ClientException(PHP_EOL . "Can`t log out." . PHP_EOL . " Fault code: " . $fault->faultcode . PHP_EOL . " Fault message: " . $fault->faultstring . PHP_EOL);
        }
        echo "Logout" . PHP_EOL;
        return $logoutresult;
    }
}

?>
