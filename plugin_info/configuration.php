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
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
?>
<form class="form-horizontal">
  <fieldset>
    <legend><i class="fas fa-user-lock"></i> {{Compte Edilkamin}}</legend>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Adresse e-mail}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Renseignez l'adresse email de l'application Edilkamin}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input type="email" class="configKey form-control" data-l1key="email" placeholder="utilisateur@exemple.com"/>
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Mot de passe}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Renseignez le mot de passe de l'applicattionb Edilkamin}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input type="password" class="configKey form-control" data-l1key="password" placeholder="********"/>
      </div>
    </div>
    <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            {{Ces informations sont utilisées uniquement pour se connecter au cloud Edilkamin afin de découvrir automatiquement vos poêles.
            Elles sont stockées de manière sécurisée par Jeedom.}}
        </div>
  </fieldset>
</form>
