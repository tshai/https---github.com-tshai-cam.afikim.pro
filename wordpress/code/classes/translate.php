<?php

class translate
{


    private $translations;

    public function __construct()
    {
        // Get the URL of the plugin directory

    }

    public function loadTranslations($locale)
    {
        $fileName = $_SERVER['DOCUMENT_ROOT'] . '/code/languages-zahi/myapp-' . $locale . '.po';
        //echo $fileName;;
        $this->translations = $this->readPoFile($fileName);
    }

    private function readPoFile($fileName)
    {
        $translations = array();
        $poContent = file_get_contents($fileName);
        if ($poContent === false) {
            // Handle error - unable to read file
            return $translations;
        }

        // Match all msgid and msgstr pairs
        preg_match_all('/msgid "(.*?)"\s+msgstr "(.*?)"/s', $poContent, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $translations[$match[1]] = $match[2];
        }

        return $translations;
    }

    public function translate($msgid)
    {
        return isset($this->translations[$msgid]) ? $this->translations[$msgid] : $msgid;
    }
}
