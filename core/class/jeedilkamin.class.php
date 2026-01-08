<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class jeedilkamin extends eqLogic {
  /*     * *************************Attributs****************************** */

  /*
  * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
  * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
  public static $_widgetPossibility = array();
  */

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration du plugin
  * Exemple : "param1" & "param2" seront cryptés mais pas "param3"
  public static $_encryptConfigKey = array('param1', 'param2');
  */

  /*     * ***********************Methode static*************************** */

  /*
  * Fonction exécutée automatiquement toutes les minutes par Jeedom
  public static function cron() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
  public static function cron5() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
  public static function cron10() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
  public static function cron15() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
  public static function cron30() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les heures par Jeedom
  public static function cronHourly() {}
  */

  /*
  * Fonction exécutée automatiquement tous les jours par Jeedom
  public static function cronDaily() {}
  */
  
  /*
  * Permet de déclencher une action avant modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function preConfig_param3( $value ) {
    // do some checks or modify on $value
    return $value;
  }
  */

  /*
  * Permet de déclencher une action après modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function postConfig_param3($value) {
    // no return value
  }
  */

  /*
   * Permet d'indiquer des éléments supplémentaires à remonter dans les informations de configuration
   * lors de la création semi-automatique d'un post sur le forum community
   public static function getConfigForCommunity() {
      // Cette function doit retourner des infos complémentataires sous la forme d'un
      // string contenant les infos formatées en HTML.
      return "les infos essentiel de mon plugin";
   }
   */
	private function createCmd($commandName, $commandDescription, $order, $type, $subType, $unite = '', $isHistorized = 0, $template = [])
	{	
		$cmd = $this->getCmd(null, $commandName);
        if (!is_object($cmd)) {
            $cmd = new jeedilkaminCmd();
            $cmd->setOrder($order);
			$cmd->setName(__($commandDescription, __FILE__));
			$cmd->setEqLogic_id($this->getId());
			$cmd->setLogicalId($commandName);
			$cmd->setType($type);
			$cmd->setSubType($subType);
			$cmd->setUnite($unite);
			$cmd->setIsHistorized($isHistorized);
			if (!empty($template)) { $cmd->setTemplate($template[0], $template[1]); }
			$cmd->save();
			log::add('jeedilkamin', 'debug', 'Add command '.$cmd->getName().' (LogicalId : '.$cmd->getLogicalId().')');
        }
    }
  /*     * *********************Méthodes d'instance************************* */

  // Fonction exécutée automatiquement avant la création de l'équipement
  public function preInsert() {
    log::add('jeedilkamin', 'debug', 'Pre Insert');
  }

  // Fonction exécutée automatiquement après la création de l'équipement
  public function postInsert() {
    log::add('jeedilkamin', 'debug', 'Post Insert');
  }

  // Fonction exécutée automatiquement avant la mise à jour de l'équipement
  public function preUpdate() {
    log::add('jeedilkamin', 'debug', 'Pre Update');
  }

  // Fonction exécutée automatiquement après la mise à jour de l'équipement
  public function postUpdate() {
    log::add('jeedilkamin', 'debug', 'Post Update');
  }

  // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
  public function preSave() {
    log::add('jeedilkamin', 'debug', '[Pre Save] Start');
	log::add('jeedilkamin', 'debug', '[Pre Save] End');
  }

  // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
  public function postSave() {
    log::add('jeedilkamin', 'debug', '[Post Save] Init');
		try {
			$countCmd = 0;
      $this->createCmd('state', 'Etat', $countCmd++, 'info', 'binary');
      $this->createCmd('set_power_on', 'Power ON', $countCmd++, 'action', 'other');
      $this->createCmd('set_power_off', 'Power OFF', $countCmd++, 'action', 'other');
			$this->createCmd('state', 'Etat', $countCmd++, 'info', 'binary');
			$this->createCmd('temperature', 'Température', $countCmd++, 'info', 'numeric', '°C');
			$this->createCmd('alarm_type', 'Alarm', $countCmd++, 'info', 'numeric');
      $this->createCmd('phase', 'Phase', $countCmd++, 'info', 'string');
			//$this->createCmd('manual_power_level', 'Puissance utilisateur', $countCmd++, 'info', 'numeric');
			$this->createCmd('pellet_autonomy_time', 'Pellet autonomie', $countCmd++, 'info', 'numeric');
      $this->createCmd('actual_power', 'Puissance', $countCmd++, 'info', 'numeric');
      $this->createCmd('is_auto', 'Mode AUTO', $countCmd++, 'info', 'binary');
      $this->createCmd('set_auto_on', 'Auto ON', $countCmd++, 'action', 'other');
      $this->createCmd('set_auto_off', 'Auto OFF', $countCmd++, 'action', 'other');
      $this->createCmd('is_relax', 'Mode Relax', $countCmd++, 'info', 'binary');
      $this->createCmd('set_relax_on', 'Relax ON', $countCmd++, 'action', 'other');
      $this->createCmd('set_relax_off', 'Relax OFF', $countCmd++, 'action', 'other');
			$param = array();
			$param['action'] = 'postSave';
			$param['macaddress'] = $this->getConfiguration('macaddress');
			$param['eqlogicid'] = $this->getId();
			$param['countcmd'] = $countCmd;
			self::sendToDaemon($param);
		} catch (Exception $e) {
			log::add('jeedilkamin', 'error', 'Exception reçue : ' . $e->getMessage());
		}

    $cron = cron::byClassAndFunction('jeedilkamin', 'updateJeedilkaminData', array('jeedilkamin_id' => intval($this->getId())));
      if (!is_object($cron)) {
          $cron = new cron();
          $cron->setClass('jeedilkamin');
          $cron->setFunction('updatejeedilkaminData');
          $cron->setOption(array('jeedilkamin_id' => intval($this->getId())));
      }
      $cron->setSchedule($this->getConfiguration('refreshCron', '*/5 * * * *'));
      $cron->save();
		log::add('jeedilkamin', 'debug', '[Post Save] End');
  }

  // Fonction exécutée automatiquement avant la suppression de l'équipement
  public function preRemove() {
    log::add('jeedilkamin', 'debug', 'Pre Remove');
  }

  // Fonction exécutée automatiquement après la suppression de l'équipement
  public function postRemove() {
    log::add('jeedilkamin', 'debug', 'Post Remove');
  }

  public static function deamon_info() {
    $return = array();
    $return['log'] = __CLASS__;
    $return['state'] = 'nok';
    $pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
    if (file_exists($pid_file)) {
        if (@posix_getsid(trim(file_get_contents($pid_file)))) {
            $return['state'] = 'ok';
        } else {
            shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
        }
    }
    $return['launchable'] = 'ok';
    $email = config::byKey('email', __CLASS__); // exemple si votre démon à besoin de la config user,
    $password = config::byKey('password', __CLASS__); // password,
    //$clientId = config::byKey('clientId', __CLASS__); // et clientId
    if ($email == '') {
        $return['launchable'] = 'nok';
        $return['launchable_message'] = __('Le nom d\'utilisateur n\'est pas configuré', __FILE__);
    } elseif ($password == '') {
        $return['launchable'] = 'nok';
        $return['launchable_message'] = __('Le mot de passe n\'est pas configuré', __FILE__);
    /*} elseif ($clientId == '') {
        $return['launchable'] = 'nok';
        $return['launchable_message'] = __('La clé d\'application n\'est pas configurée', __FILE__);*/
    }
    return $return;
  }

  public static function deamon_start() {
    self::deamon_stop();
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
        throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }

    $path = realpath(dirname(__FILE__) . '/../../resources/jeedilkamind'); // répertoire du démon à modifier
    $cmd = system::getCmdPython3(__CLASS__) . " {$path}/jeedilkamind.py"; // nom du démon à modifier
    $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));
    $cmd .= ' --socketport ' . config::byKey('socketport', __CLASS__, '51981'); // port par défaut à modifier
    $cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/jeedilkamin/core/php/jeeJeedilkamin.php'; // chemin de la callback url à modifier (voir ci-dessous)
    $cmd .= ' --email "' . trim(str_replace('"', '\"', config::byKey('email', __CLASS__))) . '"'; // on rajoute les paramètres utiles à votre démon, ici user
    $cmd .= ' --password "' . trim(str_replace('"', '\"', config::byKey('password', __CLASS__))) . '"'; // et password
    $cmd .= ' --apikey ' . jeedom::getApiKey(__CLASS__); // l'apikey pour authentifier les échanges suivants
    $cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/deamon.pid'; // et on précise le chemin vers le pid file (ne pas modifier)
    log::add(__CLASS__, 'info', 'Lancement démon');
    $result = exec($cmd . ' >> ' . log::getPathToLog('jeedilkamind') . ' 2>&1 &'); // 'template_daemon' est le nom du log pour votre démon, vous devez nommer votre log en commençant par le pluginid pour que le fichier apparaisse dans la page de config
    $i = 0;
    while ($i < 20) {
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] == 'ok') {
            break;
        }
        sleep(1);
        $i++;
    }
    if ($i >= 30) {
        log::add(__CLASS__, 'error', __('Impossible de lancer le démon, vérifiez le log', __FILE__), 'unableStartDeamon');
        return false;
    }
    message::removeAll(__CLASS__, 'unableStartDeamon');
    return true;
  }

	public static function deamon_stop() {
		$pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid'; // ne pas modifier
		if (file_exists($pid_file)) {
			$pid = intval(trim(file_get_contents($pid_file)));
			system::kill($pid);
		}
		system::kill('jeedilkamind.py'); // nom du démon à modifier
		sleep(1);
	}

	public static function sendToDaemon($params) {
	$deamon_info = self::deamon_info();
	if ($deamon_info['state'] != 'ok') {
		throw new Exception("Le démon n'est pas démarré");
	}
	$params['apikey'] = jeedom::getApiKey(__CLASS__);
	$payLoad = json_encode($params, JSON_NUMERIC_CHECK);
	$socket = socket_create(AF_INET, SOCK_STREAM, 0);
	socket_connect($socket, '127.0.0.1', config::byKey('socketport', __CLASS__, '51981'));
	socket_write($socket, $payLoad, strlen($payLoad));
	socket_close($socket);
  }

  public static function updateJeedilkaminData($_options) {
  $jeedilkamin = jeedilkamin::byId($_options['jeedilkamin_id']);
    if (is_object($jeedilkamin)) {
        $param = array();
        $param['action'] = 'updateJeedilkaminData';
        $param['macaddress'] = $jeedilkamin->getConfiguration('macaddress');
        self::sendToDaemon($param);
    }
  }

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration des équipements
  * Exemple avec le champ "Mot de passe" (password)
  public function decrypt() {
    $this->setConfiguration('password', utils::decrypt($this->getConfiguration('password')));
  }
  public function encrypt() {
    $this->setConfiguration('password', utils::encrypt($this->getConfiguration('password')));
  }
  */

  /*
  * Permet de modifier l'affichage du widget (également utilisable par les commandes)
  public function toHtml($_version = 'dashboard') {}
  */

  /*     * **********************Getteur Setteur*************************** */
}

class jeedilkaminCmd extends cmd {
  /*     * *************************Attributs****************************** */

  /*
  public static $_widgetPossibility = array();
  */

  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */

  /*
  * Permet d'empêcher la suppression des commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
  public function dontRemoveCmd() {
    return true;
  }
  */

  // Exécution d'une commande
  public function execute($_options = array()) {
    log::add('jeedilkamin', 'debug', 'Commande : ' . $this->getLogicalId());
    //log::add('jeedilkamin', 'debug', 'Option(s) : ' . $_options);
    foreach ($_options as $key => $value) {
      log::add('jeedilkamin', 'debug', 'Option(s) : ' . $key . ':' . $value);
    }
    $eqLogic = $this->getEqLogic();
    $param['macaddress'] = $eqLogic->getConfiguration('macaddress');
    $param['action'] = $this->getLogicalId();
    if (str_starts_with($this->getLogicalId(),'fan_speed')) {
      $param['speed'] = $_options['slider'];
    }elseif ($this->getLogicalId() == 'manual_power') {
      $param['manual_power'] = $_options['slider'];
    }
    jeedilkamin::sendToDaemon($param);
  }

  /*     * **********************Getteur Setteur*************************** */
}
