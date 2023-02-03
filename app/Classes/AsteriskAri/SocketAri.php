<?php

namespace App\Classes\AsteriskAri;

use Exception;

    $pathinfo = pathinfo($_SERVER['PHP_SELF']);
    $dir = $pathinfo['dirname'] . "/";
    require_once("vendor/autoload.php");

    /* START YOUR MODIFICATIONS HERE */
    class SocketAri
    {

        private $ariEndpoint;
        private $stasisClient;
        private $stasisLoop;
        private $phpariObject;
        private $stasisChannelID;
        private $dtmfSequence = "";
        private $stasisEvents;
        private $channel;
        public $stasisLogger;

        public function __construct($appname)
        {
            try {
                if (is_null($appname))
                    throw new Exception("[" . __FILE__ . ":" . __LINE__ . "] Stasis application name must be defined!", 500);

                $this->phpariObject = new Ari($appname);

                $this->ariEndpoint  = $this->phpariObject->ariEndpoint;
                $this->stasisClient = $this->phpariObject->stasisClient;
                $this->stasisLoop   = $this->phpariObject->stasisLoop;
                $this->stasisLogger = $this->phpariObject->stasisLogger;
                $this->stasisEvents = $this->phpariObject->stasisEvents;
            } catch (Exception $e) {
                echo $e->getMessage();
                exit(99);
            }
        }

        public function setDtmf($digit = NULL)
        {
            try {

                $this->dtmfSequence .= $digit;

                return TRUE;

            } catch (Exception $e) {
                return FALSE;
            }
        }


        public function StasisAppEventHandler()
        {
            $this->stasisEvents->on('StasisStart', function ($event) {
                $this->stasisLogger->notice("Event received: StasisStart");
                $this->channel = $event->channel;
                $this->stasisChannelID = $event->channel->id;
                var_dump($event->channel->caller->number);
                $this->phpariObject->channels()->channel_answer($this->stasisChannelID);
                $this->phpariObject->channels()->channel_playback($this->stasisChannelID, 'sound:hello-world', NULL, NULL, NULL, 'play1');
            });

            $this->stasisEvents->on('StasisEnd', function ($event) {
                $this->stasisLogger->notice("Event received: StasisEnd");
                if (!$this->phpariObject->channels()->channel_delete($this->stasisChannelID))
                    $this->stasisLogger->notice("Error occurred: " . $this->phpariObject->lasterror);
                  $this->stasisLoop->stop();
            });

            $this->stasisEvents->on('PlaybackStarted', function ($event) {
                $this->stasisLogger->notice("+++ PlaybackStarted +++ " . json_encode($event->playback) . "\n");
            });

            $this->stasisEvents->on('PlaybackFinished', function ($event) {


                // switch ($event->playback->id) {

                //     case "play1":
                //         $this->phpariObject->channels()->channel_playback($this->stasisChannelID, 'sound:hello-world', NULL, NULL, NULL, 'play2');
                //         break;
                //     case "play2":
                //         $this->phpariObject->channels()->channel_playback($this->stasisChannelID, 'sound:hello-world', NULL, NULL, NULL, 'end');
                //         break;
                //     case "end":
                //     $this->phpariObject->channels()->channel_continue($this->stasisChannelID, "ari-prueba", "501",'1');
                //         break;
                // }
            });

            $this->stasisEvents->on('ChannelDtmfReceived', function ($event) {
                $this->setDtmf($event->digit);
                $this->stasisLogger->notice("+++ DTMF Received +++ [" . $event->digit . "] [" . $this->dtmfSequence . "]\n");
                switch ($event->digit) {
                    case "*":
                        $this->dtmfSequence = "";
                        $this->stasisLogger->notice("+++ Resetting DTMF buffer\n");
                        break;
                    case "#":
                       $channel = $this->phpariObject->channels()->channel_originate('SIP/100',50,NULL,NULL);
                        $channels = $this->phpariObject->channels()->channel_list();
                        var_dump($channel);
                    $this->phpariObject->channels()->channel_playback($this->stasisChannelID, 'sound:hello-world', NULL, NULL, NULL, 'play1');
                    $this->stasisLoop->stop();
                    //$this->sta sisLogger->notice("+++ Playback ID: " . $this->phpariObject->playbacks()->get_playback());
                       // $this->phpariObject->channels()->channel_continue($this->stasisChannelID, "elfec", "105",'1');
                        break;
                    default:
                        break;
                }
            });
        }

        public function StasisAppConnectionHandlers()
        {
            try {
                $this->stasisClient->on("request", function () {
                    $this->stasisLogger->notice("Request received!");
                });

                $this->stasisClient->on("handshake", function () {
                    $this->stasisLogger->notice("Handshake received!");
                });

                $this->stasisClient->on("message", function ($message) {
                    $event = json_decode($message->getData());
                    $this->stasisLogger->notice('Received event: ' . $event->type);
                    $this->stasisEvents->emit($event->type, array($event));
                });

            } catch (Exception $e) {
                echo $e->getMessage();
                exit(99);
            }
        }

        public function execute()
        {
            try {
                $this->stasisClient->open();
                $this->stasisLoop->run();
            } catch (Exception $e) {
                echo $e->getMessage();
                exit(99);
            }
        }

    }

    $basicAriClient = new SocketAri("elfec2");

    $basicAriClient->stasisLogger->info("Starting Stasis Program... Waiting for handshake...");
    $basicAriClient->StasisAppEventHandler();

    $basicAriClient->stasisLogger->info("Initializing Handlers... Waiting for handshake...");
    $basicAriClient->StasisAppConnectionHandlers();

    $basicAriClient->stasisLogger->info("Connecting... Waiting for handshake...");
    $basicAriClient->execute();
    exit(0);
