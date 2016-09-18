<?php

namespace AppBundle\Twig;

class AppExtension extends \Twig_Extension
{
    public function getName()
    {
        return 'app_extension';
    }

    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('time', array($this, 'time')),
        );
    }

    public function time($time)
    {
        $hours = floor($time / 3600);
        $minutes = floor(($time / 60) % 60);
        $seconds = $time % 60;

        if ($hours) {
            $return = sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        } elseif ($minutes) {
            $return = sprintf('%d:%02d', $minutes, $seconds);
        } else {
            $return = sprintf('0:%02d', $seconds);
        }

        return $return;
    }
}
