<?php

namespace AppBundle\Twig;

use \Twig_Extension;

class JsonExtension extends Twig_Extension
{

    public function getName()
    {
        return 'json.decode';
    }

    public function getFilters() {
        return array(
            new \Twig_SimpleFilter('jsonDecode', array($this, 'jsonDecode')),
        );
    }

    public function jsonDecode($str) {
        return json_decode($str);
    }
}