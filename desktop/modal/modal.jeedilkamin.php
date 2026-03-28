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

if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

$eqLogicId = init('eqLogic_id');
$eqLogic = eqLogic::byId($eqLogicId);
if (!is_object($eqLogic)) {
    throw new Exception('{{Équipement introuvable}}');
}

$alarmTypes = [
    0  => 'Aucune alarme',
    1  => 'Entrée d\'air insuffisante',
    2  => 'RPM ventilateur fumées incorrect',
    3  => 'Pas de flamme',
    4  => 'Échec allumage',
    5  => 'Capteur débit d\'air défaillant',
    6  => 'Thermocouple défaillant',
    7  => 'Température fumées trop élevée',
    8  => 'Température poêle trop élevée',
    9  => 'Moto-réducteur défaillant',
    10 => 'Carte électronique trop chaude',
    11 => 'Pression cheminée',
    12 => 'Sonde température ambiante défaillante (1)',
    13 => 'Sonde température ambiante défaillante (2)',
    14 => 'Sonde température ambiante défaillante (3)',
    20 => 'Triac moto-réducteur défaillant',
    21 => 'Coupure de courant',
];

// Récupération du dernier JSON connu via la commande last_alarms
$cmdInfo = $eqLogic->getCmd('info', 'alarm_type');
$nbAlarms = is_object($cmdInfo) ? $cmdInfo->execCmd() : '?';
?>

<div>
    <legend><i class="fas fa-exclamation-triangle"></i> {{Historique des alarmes}} — <?php echo htmlspecialchars($eqLogic->getName()); ?></legend>
    <p class="text-muted">
        <?php echo sprintf('{{Nombre total d\'alarmes enregistrées : %s}}', $nbAlarms); ?>
    </p>

    <div class="table-responsive">
        <table class="table table-bordered table-condensed table-striped">
            <thead>
                <tr>
                    <th style="width:50px;">{{Type}}</th>
                    <th>{{Description}}</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alarmTypes as $code => $label) :
                    if ($code === 0) continue; ?>
                <tr>
                    <td><span class="label label-default"><?php echo $code; ?></span></td>
                    <td><?php echo htmlspecialchars($label); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <legend><i class="fas fa-history"></i> {{Dernières alarmes}}</legend>
    <div id="div_alarmHistory">
        <?php
        $lastAlarmsCmd = $eqLogic->getCmd('info', 'last_alarms');
        $lastAlarms = is_object($lastAlarmsCmd) ? $lastAlarmsCmd->execCmd() : '';
        if (empty($lastAlarms)) {
            echo '<p class="text-success"><i class="fas fa-check-circle"></i> {{Aucune alarme récente}}</p>';
        } else {
            $lines = explode("\n", $lastAlarms);
            echo '<ul class="list-group">';
            foreach ($lines as $line) {
                echo '<li class="list-group-item"><i class="fas fa-exclamation-circle text-warning"></i> ' . htmlspecialchars($line) . '</li>';
            }
            echo '</ul>';
        }
        ?>
    </div>
</div>
