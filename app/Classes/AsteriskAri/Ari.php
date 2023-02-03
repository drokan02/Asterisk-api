<?php

namespace App\Classes\AsteriskAri;

use App\Classes\AsteriskAri\Interfaces\applications;
use App\Classes\AsteriskAri\Interfaces\asterisk;
use App\Classes\AsteriskAri\Interfaces\bridges;
use App\Classes\AsteriskAri\Interfaces\channels;
use App\Classes\AsteriskAri\Interfaces\devicestates;
use App\Classes\AsteriskAri\Interfaces\endpoints;
use App\Classes\AsteriskAri\Interfaces\events;
use App\Classes\AsteriskAri\Interfaces\mailboxes;
use App\Classes\AsteriskAri\Interfaces\playbacks;
use App\Classes\AsteriskAri\Interfaces\recordings;
use App\Classes\AsteriskAri\Interfaces\sounds;
use Devristo\Phpws\Client\WebSocket;
use Evenement\EventEmitter;
use Exception;
use React\EventLoop\Factory;

use PestJSON;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
class Ari
{
	/** @var applications */
	private $applications;

	/** @var asterisk */
	private $asterisk;
	/** @var bridges */
	private $bridges;
	/** @var channels */
	private $channels;
	/** @var devicestates */
	private $devicestates;
	/** @var endpoints */
	private $endpoints;
	/** @var events */
	private $events;
	/** @var mailboxes */
	private $mailboxes;
	/** @var recordings */
	private $recordings;
	/** @var sounds */
	private $sounds;
	private $playbacks;
	private $configuration;

	/** @var \Evenement\EventEmitter */
	public $stasisEvents;
	/** @var Zend\Log\Logger */
	public $stasisLogger;
	/** @var React\EventLoop\LoopInterface */
	public $stasisLoop;
    /** @var VendorStatic\Devristo\Phpws\Client\WebSocket */
	public $stasisClient;
	public $debug;
	public $logfile;
	public $lasterror;
	public $lasttrace;
	public $lasterrorinfo;

	/** @var  PEST */
	public $ariEndpoint;

	/** @var  React-Guzzle */
	public $ariEndpointFactory;
	public $ariEndpointClient;
	public $ariEndpointURL;
	public $ariUsername;
	public $ariPassword;

	/**
	 * @param null $stasisApplication
	 * @param string $configFile
	 *
	 * Returns an array containing 5 objects: WebSocket, Pest, EventLoopFactory, Logger, StasisEventHandler
	 *
	 */
	public function __construct($stasisApplication = NULL, $config = "phpari.ini")
	{
		try {

			/* Get our configuration */
			$this->configuration = new AriConfig($config);

			/* Some general information */
			$this->debug = $this->configuration->general['debug'];
			$this->logfile = $this->configuration->general['logfile'];

			/* Connect to ARI server */
			$result = $this->connect($this->configuration->asterisk_ari['username'],
				$this->configuration->asterisk_ari['password'],
				$stasisApplication,
				$this->configuration->asterisk_ari['host'],
				$this->configuration->asterisk_ari['port'],
				$this->configuration->asterisk_ari['endpoint'],
				$this->configuration->asterisk_ari['transport'],
				$this->configuration->asterisk_ari['protocol']);

			return $result;


		} catch (Exception $e) {
			die("Exception raised: " . $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine());
		}

	}

	/**
	 * This function is connecting and returning a phpari client object which
	 * transferred to any of the interfaces will assist with the connection process
	 * to the Asterisk Stasis or to the Asterisk 12 web server (Channels list , End Points list)
	 * etc.
	 *
	 * @param $ariUsername
	 * @param $ariPassword
	 * @param $stasisApplication
	 * @param $ariServer
	 * @param $ariPort
	 * @param $ariEndpoint
	 * @param $ariTransport
	 *
	 * @return array
	 */
	private function connect($ariUsername, $ariPassword, $stasisApplication, $ariServer, $ariPort, $ariEndpoint, $ariTransport, $ariProtocol = "http")
	{

		try {

			$this->ariEndpoint = new PestJSON("http://" . $ariServer . ":" . $ariPort . $ariEndpoint);
			$this->ariEndpoint->setupAuth($ariUsername, $ariPassword, "basic");

			/* Create eventloop */
			$this->ariEndpointURL = $ariProtocol . "://" . $ariServer . ":" . $ariPort . $ariEndpoint;
			$this->ariUsername = $ariUsername;
			$this->ariPassword = $ariPassword;

			$this->stasisLoop = Factory::create();

			$this->stasisLogger =new Logger();

			if ($this->configuration->general['logfile'] == "console") {
				$logWriter = new Stream("php://output");
			} else {
				$logWriter = new Stream($this->configuration->general['logfile']);
			}

			$this->stasisLogger->addWriter($logWriter);

			if ($this->debug) $this->stasisLogger->debug("Initializing WebSocket Information");

			$this->stasisClient = new WebSocket($ariTransport . "://" . $ariServer . ":" . $ariPort . $ariEndpoint . "/events?api_key=" . $ariUsername . ":" . $ariPassword . "&app=" . $stasisApplication, $this->stasisLoop, $this->stasisLogger);
			if ($this->debug) $this->stasisLogger->debug("Initializing Stasis Event Emitter");
			$this->stasisEvents = new EventEmitter();


			return true;
			//return array("stasisClient" => $this->stasisClient, "stasisLoop" => $this->stasisLoop, "stasisLogger" => $this->stasisLogger, "ariEndpoint" => $this->ariEndpoint);

		} catch (Exception $e) {
			die("Exception raised: " . $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine());
		}
	}

	/**
	 * @return applications|bool - An applications object, or FALSE on failure
	 */
	public function applications()
	{
		try {
			if (!isset($this->applications)) {
				$this->applications = new applications($this);
				if ($this->debug) $this->stasisLogger->debug("applications class had been initiated");
			}

			return $this->applications;
		} catch (Exception $e) {
			if ($this->debug) $this->stasisLogger->debug("applications has failed initialization");
			$this->lasterror = "applications class failed to initialize properly";

			return FALSE;
		}
	}

	/**
	 * @return asterisk|bool - An asterisk object, or FALSE on failure
	 */
	public function asterisk()
	{
		try {
			if (!isset($this->asterisk)) {
				$this->asterisk = new asterisk($this);
				if ($this->debug) $this->stasisLogger->debug("asterisk class had been initiated");
			}

			return $this->asterisk;
		} catch (Exception $e) {
			if ($this->debug) $this->stasisLogger->debug("asterisk has failed initialization");
			$this->lasterror = "asterisk class failed to initialize properly";

			return FALSE;
		}
	}

	/**
	 * @return bridges|bool - An bridges object, or FALSE on failure
	 */
	public function bridges()
	{
		try {
			if (!($this->bridges)) {
				$this->bridges = new bridges($this);
				if ($this->debug) $this->stasisLogger->debug("bridges class had been initiated");
			}

			return $this->bridges;
		} catch (Exception $e) {
			if ($this->debug) $this->stasisLogger->debug("bridges has failed initialization");
			$this->lasterror = "bridges class failed to initialize properly";

			return FALSE;
		}
	}

	/**
	 * @return channels|bool - An channels object, or FALSE on failure
	 */
	public function channels()
	{
		try {
			if (!isset($this->channels)) {
				$this->channels = new channels($this);
				if ($this->debug) $this->stasisLogger->debug("channels class had been initiated");
			}

			return $this->channels;
		} catch (Exception $e) {
			if ($this->debug) $this->stasisLogger->debug("channels has failed initialization");
			$this->lasterror = "channels class failed to initialize properly";

			return FALSE;
		}
	}

	/**
	 * @return devicestates|bool - An devicestates object, or FALSE on failure
	 */
	public function devicestates()
	{
		try {
			if (!isset($this->devicestates)) {
				$this->devicestates = new devicestates($this);
				if ($this->debug) $this->stasisLogger->debug("devicestates class had been initiated");
			}

			return $this->devicestates;
		} catch (Exception $e) {
			if ($this->debug) $this->stasisLogger->debug("devicestates has failed initialization");
			$this->lasterror = "devicestates class failed to initialize properly";

			return FALSE;
		}
	}

	/**
	 * @return endpoints|bool - An endpoints object, or FALSE on failure
	 */
	public function endpoints()
	{
		try {
			if (!isset($this->endpoints)) {
				$this->endpoints = new endpoints($this);
				if ($this->debug) $this->stasisLogger->debug("endpoints class had been initiated");
			}

			return $this->endpoints;
		} catch (Exception $e) {
			if ($this->debug) $this->stasisLogger->debug("endpoints has failed initialization");
			$this->lasterror = "endpoints class failed to initialize properly";

			return FALSE;
		}
	}

	/**
	 * @return events|bool - An events object, or FALSE on failure
	 */
	public function events()
	{
		try {
			if (!isset($this->events)) {
				$this->events = new events($this);
				if ($this->debug) $this->stasisLogger->debug("events class had been initiated");
			}

			return $this->events;
		} catch (Exception $e) {
			if ($this->debug) $this->stasisLogger->debug("events has failed initialization");
			$this->lasterror = "events class failed to initialize properly";

			return FALSE;
		}
	}

	/**
	 * @return mailboxes|bool - An mailboxes object, or FALSE on failure
	 */
	public function mailboxes()
	{
		try {
			if (!isset($this->mailboxes)) {
				$this->mailboxes = new mailboxes($this);
				if ($this->debug) $this->stasisLogger->debug("mailboxes class had been initiated");
			}

			return $this->mailboxes;
		} catch (Exception $e) {
			if ($this->debug) $this->stasisLogger->debug("mailboxes has failed initialization");
			$this->lasterror = "mailboxes class failed to initialize properly";

			return FALSE;
		}
	}

	/**
	 * @return recordings|bool - An recordings object, or FALSE on failure
	 */
	public function recordings()
	{
		try {
			if (!isset($this->recordings)) {
				$this->recordings = new recordings($this);
				if ($this->debug) $this->stasisLogger->debug("recordings class had been initiated");
			}

			return $this->recordings;
		} catch (Exception $e) {
			if ($this->debug) $this->stasisLogger->debug("recordings has failed initialization");
			$this->lasterror = "recordings class failed to initialize properly";

			return FALSE;
		}
	}

	/**
	 * @return sounds|bool - An sounds object, or FALSE on failure
	 */
	public function sounds()
	{
		try {
			if (!isset($this->sounds)) {
				$this->sounds = new sounds($this);
				if ($this->debug) $this->stasisLogger->debug("sounds class had been initiated");
			}

			return $this->sounds;
		} catch (Exception $e) {
			if ($this->debug) $this->stasisLogger->debug("sounds has failed initialization");
			$this->lasterror = "sounds class failed to initialize properly";

			return FALSE;
		}
	}

	/**
	 * @return playbacks|bool - An playbacks object, or FALSE on failure
	 */
	public function playbacks()
	{
		try {
			if (!isset($this->playbacks)) {
				$this->playbacks = new playbacks($this);
				if ($this->debug) $this->stasisLogger->debug("playbacks class had been initiated");
			}

			return $this->playbacks;
		} catch (Exception $e) {
			if ($this->debug) $this->stasisLogger->debug("playbacks has failed initialization");
			$this->lasterror = "playbacks class failed to initialize properly";

			return false;
		}
	}

}
