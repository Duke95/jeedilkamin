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
 * Ordre optimisé pour l'affichage dashboard Jeedom.
 * Chaque entrée : logical_id => [label, type, subtype, unite, historized]
 */
function getInfoCmdMapping() {
    return [
        // --- Bloc 1 : État principal ---
        'state'                   => ['Etat',                    'info', 'binary',  '',    0],
        'phase'                   => ['Phase',                   'info', 'string',  '',    0],
        // --- Bloc 2 : Températures ---
        'temperature'             => ['Température ambiante',    'info', 'numeric', '°C',  1],
        'target_temperature'      => ['Consigne',                'info', 'numeric', '°C',  0],
        // --- Bloc 3 : Puissance ---
        'actual_power'            => ['Puissance',               'info', 'numeric', '',    1],
        'manual_power_level'      => ['Puissance manuelle',      'info', 'numeric', '',    0],
        // --- Bloc 4 : Modes ---
        'is_auto'                 => ['Mode AUTO',               'info', 'binary',  '',    0],
        'is_relax'                => ['Mode Relax',              'info', 'binary',  '',    0],
        // --- Bloc 5 : Alertes ---
        'is_pellet_in_reserve'    => ['Réserve pellets',         'info', 'binary',  '',    1],
        'is_cat_service_required' => ['Entretien requis',        'info', 'binary',  '',    0],
        'alarm_type'              => ['Alarme',                  'info', 'numeric', '',    0],
        'is_cleaning_in_progress' => ['Nettoyage en cours',      'info', 'binary',  '',    0],
        // --- Bloc 6 : Mesures ---
        'thermocouple'            => ['Température fumées',      'info', 'numeric', '°C',  1],
        'air_pressure'            => ['Pression air',            'info', 'numeric', 'Pa',  1],
        'pellet_autonomy_time'    => ['Autonomie pellets',       'info', 'numeric', 'min', 1],
        // --- Bloc 7 : Infos secondaires ---
        'is_crono_active'         => ['Chrono actif',            'info', 'binary',  '',    0],
        'is_standby_active'       => ['Standby actif',           'info', 'binary',  '',    0],
        'is_airkare_active'       => ['Airkare actif',           'info', 'binary',  '',    0],
        'board_temperature'       => ['Température carte',       'info', 'numeric', '°C',  0],
        'power_ons'               => ['Nb allumages',            'info', 'numeric', '',    1],
        'last_refresh'            => ['Dernier rafraîchissement','info', 'string',  '',    0],
    ];
}

/**
 * Mapping des commandes action fixes.
 * Ordre calqué sur les blocs info pour cohérence dashboard.
 * Chaque entrée : logical_id => [label, subtype, linked_info_key, min, max]
 */
function getActionCmdMapping() {
    return [
        // --- Bloc 1 : Contrôle principal ---
        'set_power_on'           => ['Power ON',             'other',  'state',              0,  0],
        'set_power_off'          => ['Power OFF',            'other',  'state',              0,  0],
        // --- Bloc 2 : Température ---
        'set_target_temperature' => ['Température consigne', 'slider', 'target_temperature', 16, 22],
        // --- Bloc 4 : Modes ---
        'set_auto_on'            => ['Auto ON',              'other',  'is_auto',            0,  0],
        'set_auto_off'           => ['Auto OFF',             'other',  'is_auto',            0,  0],
        'set_relax_on'           => ['Relax ON',             'other',  'is_relax',           0,  0],
        'set_relax_off'          => ['Relax OFF',            'other',  'is_relax',           0,  0],
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

    // Bloc 1 : État + actions power (intercalées pour cohérence dashboard)
    $cmdIds['state'] = createCmd($eqLogic, 'state', 'Etat', $order++, 'info', 'binary', '', 0, 0, '', 0);
    createCmd($eqLogic, 'set_power_on',  'Power ON',  $order++, 'action', 'other', $cmdIds['state']);
    createCmd($eqLogic, 'set_power_off', 'Power OFF', $order++, 'action', 'other', $cmdIds['state']);
    createCmd($eqLogic, 'phase', 'Phase', $order++, 'info', 'string', '', 0, 0, '', 0);

    // Bloc 2 : Températures + consigne
    $cmdIds['temperature']      = createCmd($eqLogic, 'temperature',      'Température ambiante', $order++, 'info', 'numeric', '', 0, 0, '°C', 1);
    $cmdIds['target_temperature']= createCmd($eqLogic, 'target_temperature','Consigne',            $order++, 'info', 'numeric', '', 0, 0, '°C', 0);
    createCmd($eqLogic, 'set_target_temperature', 'Température consigne', $order++, 'action', 'slider', $cmdIds['target_temperature'], 16, 22);

    // Bloc 3 : Puissance + slider
    $cmdIds['actual_power']     = createCmd($eqLogic, 'actual_power',     'Puissance',            $order++, 'info', 'numeric', '', 0, 0, '',    1);
    createCmd($eqLogic, 'manual_power_level', 'Puissance manuelle', $order++, 'info', 'numeric', '', 0, 0, '', 0);
    $maxPower = 1;
    while (isset($infos['nvm']['cat_parameters']['fan_activation_enviroment_1_p' . ($maxPower + 1)])) {
        $maxPower++;
    }
    createCmd($eqLogic, 'manual_power', 'Puissance utilisateur', $order++, 'action', 'slider', '', 1, $maxPower);

    // Bloc 4 : Modes AUTO + Relax
    $cmdIds['is_auto']  = createCmd($eqLogic, 'is_auto',  'Mode AUTO',  $order++, 'info', 'binary', '', 0, 0, '', 0);
    createCmd($eqLogic, 'set_auto_on',  'Auto ON',  $order++, 'action', 'other', $cmdIds['is_auto']);
    createCmd($eqLogic, 'set_auto_off', 'Auto OFF', $order++, 'action', 'other', $cmdIds['is_auto']);
    $cmdIds['is_relax'] = createCmd($eqLogic, 'is_relax', 'Mode Relax', $order++, 'info', 'binary', '', 0, 0, '', 0);
    createCmd($eqLogic, 'set_relax_on',  'Relax ON',  $order++, 'action', 'other', $cmdIds['is_relax']);
    createCmd($eqLogic, 'set_relax_off', 'Relax OFF', $order++, 'action', 'other', $cmdIds['is_relax']);

    // Bloc 5 : Fans dynamiques
    $nbFans = $infos['nvm']['installer_parameters']['fans_number'] ?? 0;
    for ($i = 1; $i <= $nbFans; $i++) {
        $maxLevel   = $infos['nvm']['oem_parameters']['fan_' . $i . '_max_level']
                   ?? $infos['nvm']['oem_parameters']['fan_1_max_level']
                   ?? 4;
        $maxSpeed   = $maxLevel + 1;
        $engineType = $infos['nvm']['oem_parameters']['fan_' . $i . '_engine_type'] ?? 4;
        $minSpeed   = ($engineType == 2) ? 1 : 0;
        $fanInfoId  = createCmd($eqLogic, 'fan' . $i, 'Fan ' . $i, $order++, 'info', 'numeric', '', 0, 0, '', 0);
        createCmd($eqLogic, 'fan_speed' . $i, 'Vitesse fan ' . $i, $order++, 'action', 'slider', $fanInfoId, $minSpeed, $maxSpeed);
    }

    // Bloc 6 : Alertes
    createCmd($eqLogic, 'is_pellet_in_reserve',    'Réserve pellets',    $order++, 'info', 'binary',  '', 0, 0, '', 1);
    createCmd($eqLogic, 'is_cat_service_required', 'Entretien requis',   $order++, 'info', 'binary',  '', 0, 0, '', 0);
    createCmd($eqLogic, 'alarm_type',              'Alarme',             $order++, 'info', 'numeric', '', 0, 0, '', 0);
    createCmd($eqLogic, 'is_cleaning_in_progress', 'Nettoyage en cours', $order++, 'info', 'binary',  '', 0, 0, '', 0);

    // Bloc 7 : Mesures
    createCmd($eqLogic, 'thermocouple',         'Température fumées', $order++, 'info', 'numeric', '', 0, 0, '°C',  1);
    createCmd($eqLogic, 'air_pressure',         'Pression air',       $order++, 'info', 'numeric', '', 0, 0, 'Pa',  1);
    createCmd($eqLogic, 'pellet_autonomy_time', 'Autonomie pellets',  $order++, 'info', 'numeric', '', 0, 0, 'min', 1);

    // Bloc 8 : Infos secondaires
    createCmd($eqLogic, 'is_crono_active',   'Chrono actif',            $order++, 'info', 'binary',  '', 0, 0, '', 0);
    createCmd($eqLogic, 'is_standby_active', 'Standby actif',           $order++, 'info', 'binary',  '', 0, 0, '', 0);
    createCmd($eqLogic, 'is_airkare_active', 'Airkare actif',           $order++, 'info', 'binary',  '', 0, 0, '', 0);
    createCmd($eqLogic, 'board_temperature', 'Température carte',       $order++, 'info', 'numeric', '', 0, 0, '°C', 0);
    createCmd($eqLogic, 'power_ons',         'Nb allumages',            $order++, 'info', 'numeric', '', 0, 0, '',   1);
    createCmd($eqLogic, 'last_refresh',      'Dernier rafraîchissement',$order++, 'info', 'string',  '', 0, 0, '',   0);

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
        // Stockage du log d'alarmes pour affichage dans la modal
        $eqLogic->setConfiguration('alarms_log', json_encode($infos['nvm']['alarms_log'] ?? []));
        // Stockage des compteurs pour affichage dans la modal
        $eqLogic->setConfiguration('counters_log', json_encode([
            'total_counters'   => $infos['nvm']['total_counters']   ?? [],
            'service_counters' => $infos['nvm']['service_counters'] ?? [],
        ]));
        $eqLogic->save();
    }

} catch (Exception $e) {
    log::add('jeedilkamin', 'error', displayException($e));
}
