<?php
/* This file is part of Jeedom.
 * Jeedom is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License
 */

if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

$eqLogic = eqLogic::byId(init('eqLogic_id'));
if (!is_object($eqLogic)) {
    throw new Exception('{{Équipement introuvable}}');
}

$alarmTypes = [
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

$alarmsLog = json_decode($eqLogic->getConfiguration('alarms_log', '{}'), true);
$alarms = array_filter($alarmsLog['alarms'] ?? [], fn($a) => $a['timestamp'] > 0);
usort($alarms, fn($a, $b) => $b['timestamp'] - $a['timestamp']);

// Grouper par année
$byYear = [];
foreach ($alarms as $alarm) {
    $year = date('Y', $alarm['timestamp']);
    $byYear[$year][] = $alarm;
}
krsort($byYear);
?>
<legend><i class="fas fa-exclamation-triangle"></i> {{Historique des alarmes}} — <?php echo htmlspecialchars($eqLogic->getName()); ?></legend>
<p class="text-muted">
    <?php echo sprintf('{{Total : %d alarmes enregistrées}}', count($alarms)); ?>
</p>

<?php if (empty($byYear)) : ?>
    <p class="text-success"><i class="fas fa-check-circle"></i> {{Aucune alarme enregistrée}}</p>
<?php else : ?>
    <div class="panel-group" id="accordion_alarms">
    <?php foreach ($byYear as $year => $yearAlarms) : ?>
        <div class="panel panel-default">
            <div class="panel-heading" style="cursor:pointer;" data-toggle="collapse" data-target="#collapse_<?php echo $year; ?>">
                <h4 class="panel-title">
                    <i class="fas fa-calendar"></i>
                    <?php echo $year; ?>
                    <span class="badge pull-right"><?php echo count($yearAlarms); ?></span>
                </h4>
            </div>
            <div id="collapse_<?php echo $year; ?>" class="panel-collapse collapse <?php echo ($year == date('Y')) ? 'in' : ''; ?>">
                <table class="table table-bordered table-condensed table-striped" style="margin-bottom:0;">
                    <thead>
                        <tr>
                            <th style="width:160px;">{{Date}}</th>
                            <th style="width:60px;">{{Code}}</th>
                            <th>{{Description}}</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($yearAlarms as $alarm) : ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', $alarm['timestamp']); ?></td>
                            <td><span class="label label-warning"><?php echo $alarm['type']; ?></span></td>
                            <td><?php echo htmlspecialchars($alarmTypes[$alarm['type']] ?? 'Type inconnu (' . $alarm['type'] . ')'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
