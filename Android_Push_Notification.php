<?php
/**
 * Android_Push_Notification Class
 *
 * @category  Request Driver
 * @package   Android_Push_Notification
 * @author    Stephanie Schmidt <littlebeehigbee@gmail.com>
 * @copyright Copyright (c) 2014
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @version   1.0
 **/

include(dirname(__FILE__).'/Request_Driver_Curl.php');

/**
 * Class Android_Push_Notification
 */
class Android_Push_Notification
{
    /**
     * @var
     */
    public $packageId;

    /**
     * @var
     */
    public $apiKey;

    /**
     * @var
     */
    public $resource;

    /**
     * @var
     */
    protected $devices;

    /**
     * @var mixed
     */
    protected $message;

    /**
     * @var mixed
     */
    protected $badge;

    /**
     * @var mixed
     */
    protected $sound;

    /**
     * @var array
     */
    protected $payload;

    /**
     * @var array
     */
    protected $addtl_payload;


	/**
     * @param $registrationIds
     * @param $message
     * @param null $badge
     * @param null $sound
     * @param null $addtlPayload
     * @param null $badgeCallback
     */
    function __construct($registrationIds, $message, $badge=null, $sound=null, $addtlPayload=null, $badgeCallback=null)
    {
        $this->registrationIds = $registrationIds;
        $this->message = $message;
        $this->badge = $badge;
        $this->sound = $sound;
        $this->addtlPayload = $addtlPayload;
        $this->badgeCallback = $badgeCallback;
    }


	/**
     * @return bool
     * @throws Exception
     */
    public function pushNotification()
    {
        //set up data array to be pushed to GCM
        $limit = 999;
        $responses = array();


        //we have a individual badge callback so send each push individually
        if(is_array($this->badgeCallback) && !empty($this->badgeCallback))
        {
            foreach($this->registrationIds as $registrationId){

                $payload = array(
                    'registration_ids' => $registrationId,
                    "time_to_live" => 108,
                    "delay_while_idle" => true,
                    'data' => array("message" => $this->message),
                );

                //build the custom payload
                $class = $this->badge_callback[0];
                $method = $this->badge_callback[1];
                $object = new $class;
                $result = call_user_func_array(array($object, $method), $registrationId);
                if($result && is_int((int)$result)){
                    $this->badge = $result;
                }

                $payload['aps'] = array(
                    'alert' => $this->message,
                    'badge' => $this->badge,
                    'sound' => $this->sound,
                );
                if (!is_null($this->addtl_payload)) {
                    $payload['app'] = $this->addtl_payload;
                }

                //render message in correct format to send
                $payload = json_encode($payload);

                //load correct request driver and send request
                $driver = new Request_Driver_Curl($payload);

                $driver->setHeader("Authorization", "key=" . $this->$this->notification->apiKey);
                $driver->setHeader("Content-Type", "application/json");
                $driver->setMethod('post');

                $response = $driver->execute();

                if ($response) {
                    $responses['true'][] = $registrationId;
                } else {
                    $responses['false'][] = $registrationId;
                }
            }
        //no individual badge callback so lets push in mass
        } else
        {
            $chunks = array_chunk($this->registrationIds, $limit);
            $responses = array();
            foreach($chunks as $chunk){

                $payload = array(
                    'registration_ids' => $chunk,
                    "time_to_live" => 108,
                    "delay_while_idle" => true,
                    'data' => array("message" => $this->message),
                );

                if (!is_null($this->addtl_payload)) {
                    $payload['app'] = $this->addtl_payload;
                }

                //render message in correct format to send
                $payload = json_encode($payload);

                //load correct request driver and send request
                $driver = new Request_Driver_Curl($payload);

                $driver->setHeader("Authorization", "key=" . $this->$this->notification->apiKey);
                $driver->setHeader("Content-Type", "application/json");
                $driver->setMethod('post');

                $response = $driver->execute();

                if ($response) {
                    foreach($chunk as $deviceToken){
                        $responses['true'][] = $deviceToken;
                    }
                } else {
                    foreach($chunk as $deviceToken){
                        $responses['false'][] = $deviceToken;
                    }
                }
            }
        }

        if(empty($responses['false'])){
            //no pushes failed
            $this->response = true;
        } else {
            //something went wrong
            $this->response = false;
            throw new Exception('Send failed for these regIds: '.implode(', ',$responses['false']));
        }
        return $this->response;
    }
}
