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
function createCmd($eqLogic, $commandName, $commandDescription, $order, $type, $subType, $value = '', $fan_ventilation = 0, $unite = '', $isHistorized = 0, $template = [])
{
    $cmd = $eqLogic->getCmd(null, $commandName);
    if (!is_object($cmd)) {
        $cmd = new jeedilkaminCmd();
        $cmd->setOrder($order);
        $cmd->setName(__($commandDescription, __FILE__));
        $cmd->setEqLogic_id($eqLogic->getId());
        $cmd->setLogicalId($commandName);
        $cmd->setType($type);
        $cmd->setSubType($subType);
        if ($type == 'action') {
            if ($subType == 'slider') {
                if (str_ends_with($commandName,'1')) {}
                    $cmd->setConfiguration('minValue' , 1);
                else {
                    $cmd->setConfiguration('minValue' , 0);
                }
                $cmd->setConfiguration('maxValue' , $fan_ventilation);
            }
        }
        $cmd->setUnite($unite);
        $cmd->setValue($value);
        $cmd->setIsHistorized($isHistorized);
        if (!empty($template)) { 
            $cmd->setTemplate($template[0], $template[1]);
        }
        $cmd->save();
        return $cmd->getId();
        log::add('jeedilkamin', 'debug', 'Add command '. $cmd->getName() . ' (LogicalId : ' . $cmd->getLogicalId() . ')');
    }
}

/*function createCmd($params)
{
    $cmd = $params['eqLogic']->getCmd(null, $params['commandName']);
    if (!is_object($cmd)) {
        $cmd = new jeedilkaminCmd();
        $cmd->setOrder($params['order']);
        $cmd->setName(__($params['commandDescription'], __FILE__));
        $cmd->setEqLogic_id($eqLogic->getId());
        $cmd->setLogicalId($params['commandName']);
        $cmd->setType($params['type']);
        $cmd->setSubType($params['subType']);
        $cmd->setUnite($params['unite']);
        if ($params['type'] == 'action') {
            if ($params['tysubType'] == 'slider') {
                $cmd->setConfiguration('minValue' , $params['minValue']);
                $cmd->setConfiguration('maxValue' , $params['maxValue']);
            }
        } else {

        }
        $cmd->setValue($params['value']);
        $cmd->setIsHistorized($params['isHistorized']);
        if (!empty($params['template'])) { 
            $cmd->setTemplate($params['template'][0], $params['template'][1]);
        }
        $cmd->save();
        log::add('jeedilkamin', 'debug', 'Add command '. $cmd->getName() . ' (LogicalId : ' . $cmd->getLogicalId() . ')');
        return $cmd;
    }
}*/

try
{
    require_once __DIR__ . "/../../../../core/php/core.inc.php";

    if (!jeedom::apiAccess(init('apikey'), 'jeedilkamin')) {
        echo __('Vous n\'êtes pas autorisé à effectuer cette action', __FILE__);
        die();
    }
    if (init('test') != '') {
        echo 'OK';
        die();
    }
    $result = json_decode(file_get_contents("php://input"), true);
    if (!is_array($result)) {
        die();
    }
    log::add('jeedilkamin', 'debug', 'Start Info');
    $infos = json_decode($result['infos'], true);
    $nbFans = $infos['nvm']['installer_parameters']['fans_number'];
    if (isset($result['eqlogicid']) && isset($result['countcmd'])) {
        $nbCmd = $result['countcmd'];
        $edilkamin = eqLogic::byId($result['eqlogicid']);
        for ($i=1; $i<=$nbFans; $i++) {
            $id = createCmd($edilkamin, 'fan' . $i, 'Fan' . $i, $nbCmd + $i, 'info', 'string');
            createCmd($edilkamin, 'fan_speed' . $i, 'Fan speed' . $i, $nbCmd + $i, 'action', 'slider', $id, $infos['nvm']['user_parameters']['fan_' . $i .'_ventilation']);
        }
    }

    $eqLogics = eqLogic::byTypeAndSearchConfiguration('jeedilkamin', $result['mac_address']);
    foreach ($eqLogics as $eqLogic) {
        foreach ($result['refresh_infos'] as $key => $value) {
            $eqLogic->checkAndUpdateCmd($key,$value);
        }
    }
} catch (Exception $e) {
    log::add('jeedilkamin', 'error', displayException($e));
}