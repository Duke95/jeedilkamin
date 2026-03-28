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

$countersLog = json_decode($eqLogic->getConfiguration('counters_log', '{}'), true);
$total   = $countersLog['total_counters']   ?? [];
$service = $countersLog['service_counters'] ?? [];

function formatHours($minutes) {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
}
?>

<legend><i class="fas fa-tachometer-alt"></i> {{Compteurs}} — <?php echo htmlspecialchars($eqLogic->getName()); ?></legend>

<div class="row">
    <div class="col-sm-6">
        <legend><i class="fas fa-history"></i> {{Compteurs totaux}}</legend>
        <table class="table table-bordered table-condensed table-striped">
            <thead>
                <tr><th>{{Indicateur}}</th><th class="text-right">{{Valeur}}</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><i class="fas fa-power-off"></i> {{Nombre d'allumages}}</td>
                    <td class="text-right"><span class="badge"><?php echo $total['power_ons'] ?? '-'; ?></span></td>
                </tr>
                <?php foreach (['p1'=>'P1','p2'=>'P2','p3'=>'P3','p4'=>'P4','p5'=>'P5'] as $key => $label) :
                    $val = $total[$key . '_working_time'] ?? null; ?>
                <tr>
                    <td><i class="fas fa-clock"></i> {{Temps fonctionnement}} <?php echo $label; ?></td>
                    <td class="text-right"><?php echo $val !== null ? formatHours($val) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="col-sm-6">
        <legend><i class="fas fa-wrench"></i> {{Compteurs depuis dernier entretien}}</legend>
        <table class="table table-bordered table-condensed table-striped">
            <thead>
                <tr><th>{{Indicateur}}</th><th class="text-right">{{Valeur}}</th></tr>
            </thead>
            <tbody>
                <?php foreach (['p1'=>'P1','p2'=>'P2','p3'=>'P3','p4'=>'P4','p5'=>'P5'] as $key => $label) :
                    $val = $service[$key . '_working_time'] ?? null; ?>
                <tr>
                    <td><i class="fas fa-clock"></i> {{Temps fonctionnement}} <?php echo $label; ?></td>
                    <td class="text-right"><?php echo $val !== null ? formatHours($val) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $serviceHours = array_sum(array_filter([
            $service['p1_working_time'] ?? 0,
            $service['p2_working_time'] ?? 0,
            $service['p3_working_time'] ?? 0,
            $service['p4_working_time'] ?? 0,
            $service['p5_working_time'] ?? 0,
        ])) / 60;
        $maxServiceHours = 2000;
        $pct = min(100, round($serviceHours / $maxServiceHours * 100));
        $color = $pct < 70 ? 'success' : ($pct < 90 ? 'warning' : 'danger');
        ?>
        <p class="text-muted" style="margin-top:10px;">{{Total depuis entretien}} : <strong><?php echo round($serviceHours); ?>h</strong> / <?php echo $maxServiceHours; ?>h</p>
        <div class="progress">
            <div class="progress-bar progress-bar-<?php echo $color; ?>" style="width:<?php echo $pct; ?>%"><?php echo $pct; ?>%</div>
        </div>
    </div>
</div>
