<?php

namespace Translator\Vendor\Translator\Script\Core;

defined('TRANSLATOR_SCRIPT_TRANSLATION') or die();

class Boundary
{
    /**
     * @var null|Boundary
     */
    private static $_instance = null;

    /**
     * Generated boundary
     *
     * @var string|null
     */
    protected $boundary = null;

    /**
     * Array of boundaries to store
     * @var array
     */
    protected $fields = [];

    /**
     * Post field content
     *
     * @var string
     */
    protected $content = '';

    /**
     * Retrieve singleton instance
     *
     * @return Boundary|null
     */
    public static function getInstance() {

        if(is_null(self::$_instance)) {
            self::$_instance = new Boundary();
        }

        return self::$_instance;
    }

    public function __construct()
    {
        $this->boundary = '------'.substr(str_shuffle(str_repeat($c='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(15/strlen($c)) )),1, 10);
    }

    /**
     * Return generated boundary
     *
     * @return string|null
     */
    public function getBoundary()
    {
        return $this->boundary;
    }

    /**
     * Add a field to the fields array
     *
     * @param $name
     * @param $value
     * @return void
     */
    public function addPostFields($name, $value)
    {
        $this->fields[$name] = $value;
    }

    /**
     * Return the end of a boundary
     * @return string
     */
    protected function endPostFields()
    {
        return '--'.$this->boundary.'--';
    }

    /**
     * Retrieve the Content-Disposition header
     *
     * @return string
     */
    public function getContent()
    {
        $content = '';
        foreach ($this->fields as $name => $value) {
            $content .= '--'.$this->boundary."\r\n";
            $content .= "Content-Disposition: form-data; name=\"".$name."\"\r\n\r\n".$value."\r\n";
        }
        $content .= $this->endPostFields();
        return $content;
    }
}
