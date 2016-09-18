<?php

namespace AppBundle\TaskDescription;

use Webdevvie\PheanstalkTaskQueueBundle\TaskDescription\AbstractTaskDescription;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\ExclusionPolicy;

/**
 * @ExclusionPolicy("all")
 */
class YoutubeQueueTaskDescription extends AbstractTaskDescription
{
    /**
     * @var string
     * @Type("string")
     * @SerializedName("hash")
     * @Expose
     */
    public $hash;

    /**
     * @var string
     * @Type("string")
     * @SerializedName("format")
     * @Expose
     */
    public $format;

    /**
     * The command to pass to the console.
     *
     * @var string
     */
    protected $command = 'youtube:get';

    /**
     * the options to pass to the console command.
     *
     * @var array
     */
    protected $commandOptions = array();

    /**
     * The arguments to pass to the console command.
     *
     * @var array
     */
    protected $commandArguments = array('hash', 'format');
}
