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

/**
 * Mapping déclaratif des commandes info à créer automatiquement.
 * Chaque entrée : logical_id => [label, type, subtype, unite, historized]
 * Les commandes dynamiques (fans) sont gérées séparément.
 */
function getInfoCmdMapping() {
    return [
        // État général
        'state'                 => ['Etat',                  'info', 'binary',  '',    0],
        'phase'                 => ['Phase',                 'info', 'string',  '',    0],
        'actual_power'          => ['Puissance',             'info', 'numeric', '',    1],
        'alarm_type'            => ['Alarme',                'info', 'numeric', '',    0],
        // Températures
        'temperature'           => ['Température ambiante',  'info', 'numeric', '°C', 1],
        'target_temperature'    => ['Consigne',              'info', 'numeric', '°C', 0],
        // Modes (binaires avec actions associées)
        'is_auto'               => ['Mode AUTO',             'info', 'binary',  '',    0],
        'is_relax'              => ['Mode Relax',            'info', 'binary',  '',    0],
        // Modes (binaires sans action — lecture seule)
        'is_crono_active'       => ['Chrono actif',          'info', 'binary',  '',    0],
        'is_standby_active'     => ['Standby actif',         'info', 'binary',  '',    0],
        'is_airkare_active'     => ['Airkare actif',         'info', 'binary',  '',    0],
        // Alertes
        'is_pellet_in_reserve'  => ['Réserve pellets',       'info', 'binary',  '',    1],
        // Pellets & compteurs
        'pellet_autonomy_time'  => ['Autonomie pellets',     'info', 'numeric', 'min', 1],
        'manual_power_level'    => ['Puissance manuelle',    'info', 'numeric', '',    0],
        // Compteurs totaux
        'power_ons'             => ['Nb allumages',          'info', 'numeric', '',    1],
        // Alarmes
        'last_alarms'           => ['Dernières alarmes',     'info', 'string',  '',    0],
    ];
}

/**
 * Mapping des commandes action fixes.
 * Chaque entrée : logical_id => [label, subtype, linked_info_key, min, max]
 */
function getActionCmdMapping() {
    return [
        'set_power_on'           => ['Power ON',             'other',  'state',              0,  0],
        'set_power_off'          => ['Power OFF',            'other',  'state',              0,  0],
        'set_auto_on'            => ['Auto ON',              'other',  'is_auto',            0,  0],
        'set_auto_off'           => ['Auto OFF',             'other',  'is_auto',            0,  0],
        'set_relax_on'           => ['Relax ON',             'other',  'is_relax',           0,  0],
        'set_relax_off'          => ['Relax OFF',            'other',  'is_relax',           0,  0],
        'set_target_temperature' => ['Température consigne', 'slider', 'target_temperature', 16, 22],
    ];
}

function createCmd($eqLogic, $logicalId, $label, $order, $type, $subType, $linkedId = '', $min = 0, $max = 0, $unite = '', $isHistorized = 0)
{
    $cmd = $eqLogic->getCmd(null, $logicalId);
    if (!is_object($cmd)) {
        $cmd = new jeedilkaminCmd();
        $cmd->setOrder($order);
        $cmd->setName(__($label, __FILE__));
        $cmd->setEqLogic_id($eqLogic->getId());
        $cmd->setLogicalId($logicalId);
        $cmd->setType($type);
        $cmd->setSubType($subType);
        $cmd->setUnite($unite);
        $cmd->setIsHistorized($isHistorized);
        if ($linkedId !== '') {
            $cmd->setValue($linkedId);
        }
        if ($subType === 'slider') {
            $cmd->setConfiguration('minValue', $min);
            $cmd->setConfiguration('maxValue', $max);
        }
        $cmd->save();
        log::add('jeedilkamin', 'debug', 'Add command ' . $cmd->getName() . ' (LogicalId: ' . $logicalId . ')');
        return $cmd->getId();
    }
    return $cmd->getId();
}

function createAllCommands($eqLogic, $infos)
{
    $order = 0;
    $cmdIds = [];

    // Commandes info fixes
    foreach (getInfoCmdMapping() as $logicalId => [$label, $type, $subType, $unite, $historized]) {
        $cmdIds[$logicalId] = createCmd($eqLogic, $logicalId, $label, $order++, $type, $subType, '', 0, 0, $unite, $historized);
    }

    // Fans dynamiques selon le nombre déclaré dans le JSON
    $nbFans = $infos['nvm']['installer_parameters']['fans_number'] ?? 0;
    for ($i = 1; $i <= $nbFans; $i++) {
        // fan_X_max_level est un index 0-based → max réel = valeur + 1
        $maxLevel = $infos['nvm']['oem_parameters']['fan_' . $i . '_max_level']
                 ?? $infos['nvm']['oem_parameters']['fan_1_max_level']
                 ?? 4;
        $maxSpeed = $maxLevel + 1;
        // fan_engine_type 2 = ventilateur principal (min=1, ne peut pas s'arrêter)
        // fan_engine_type 4 = ventilateur canalisé (min=0, peut être coupé)
        $engineType = $infos['nvm']['oem_parameters']['fan_' . $i . '_engine_type'] ?? 4;
        $minSpeed = ($engineType == 2) ? 1 : 0;
        $fanInfoId = createCmd($eqLogic, 'fan' . $i, 'Fan ' . $i, $order++, 'info', 'numeric', '', 0, 0, '', 0);
        createCmd($eqLogic, 'fan_speed' . $i, 'Vitesse fan ' . $i, $order++, 'action', 'slider', $fanInfoId, $minSpeed, $maxSpeed);
    }

    // Commandes action fixes
    foreach (getActionCmdMapping() as $logicalId => [$label, $subType, $linkedKey, $min, $max]) {
        $linkedId = ($linkedKey !== '' && isset($cmdIds[$linkedKey])) ? $cmdIds[$linkedKey] : '';
        createCmd($eqLogic, $logicalId, $label, $order++, 'action', $subType, $linkedId, $min, $max);
    }

    // Puissance manuelle : min=1 (P1), max=5 (P5) — constante du protocole Edilkamin
    createCmd($eqLogic, 'manual_power', 'Puissance utilisateur', $order++, 'action', 'slider', '', 1, 5);

    log::add('jeedilkamin', 'info', 'Commandes créées pour eqLogic ' . $eqLogic->getId());
}

try {
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

    $infos = json_decode($result['infos'], true);
    if (!is_array($infos)) {
        log::add('jeedilkamin', 'error', 'Impossible de décoder le JSON infos');
        die();
    }

    // Création automatique des commandes au premier postSave
    if (isset($result['eqlogicid'])) {
        $eqLogic = eqLogic::byId($result['eqlogicid']);
        if (is_object($eqLogic)) {
            createAllCommands($eqLogic, $infos);
        }
    }

    // Mise à jour des valeurs pour tous les équipements correspondant à cette MAC
    $eqLogics = eqLogic::byTypeAndSearchConfiguration('jeedilkamin', $result['mac_address'] ?? '');
    foreach ($eqLogics as $eqLogic) {
        foreach (($result['refresh_infos'] ?? []) as $key => $value) {
            $eqLogic->checkAndUpdateCmd($key, $value);
        }
    }

} catch (Exception $e) {
    log::add('jeedilkamin', 'error', displayException($e));
}
