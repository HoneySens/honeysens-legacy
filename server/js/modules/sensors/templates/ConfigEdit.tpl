<% if(isModal()) { %>
    <div class="row">
        <div class="col-sm-12">
        <h1 class="page-header"><span class="glyphicon glyphicon-plus"></span>&nbsp;Spezifische Konfiguration <% if(isEdit()) { %>bearbeiten<% } else { %>hinzuf&uuml;gen<% } %></h1>
<% } %>
<form class="form">
    <% if(isModal()) { %>
    <div class="form-group">
        <label for="sensorConfigSensor" class="control-label">Sensor</label>
        <% if(isEdit()) { %>
            <p class="form-control-static"><%- showSensor() %></p>
        <% } else { %>
            <select class="form-control sensorConfigSensor">
                <% _(unconfiguredSensors).each(function(s) { %>
                    <option value="<%- s.id %>"><%- s.name %></option>
                <% }); %>
            </select>
        <% } %>
    </div>
    <% } %>
    <div class="panel-group" id="<% if(isModal()) { %>configGroupsSingle<% } else { %>configGroupsGlobal<% } %>">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <a data-toggle="collapse" data-parent="<% if(isModal()) { %>#configGroupsSingle<% } else { %>#configGroupsGlobal<% } %>" href="<% if(isModal()) { %>#configGroupIntervalSingle<% } else { %>#configGroupIntervalGlobal<% } %>">Updateintervall</a>
                </h4>
            </div>
            <div id="<% if(isModal()) { %>configGroupIntervalSingle<% } else { %>configGroupIntervalGlobal<% } %>" class="panel-collapse collapse">
                <div class="panel-body">
                    <p>Das Updateintervall gibt an, in welchen zeitlichen Abst&auml;nden die Sensoren den HoneySens-Server kontaktieren sollen,
                        um Statusmeldungen abzuliefern und ihre aktuelle Konfiguration oder auch Firmwareupdates zu empfangen.</p>
                    <label for="sensorConfigInterval" class="control-label">Updateintervall in Minuten:</label>
                    <div class="form-group">
                        <div class="input-group">
                            <input type="text" class="form-control input-sm sensorConfigInterval" name="sensorConfigInterval" value="<%- interval %>" <% if(!_.templateHelpers.isAllowed('sensorconfigs', 'update')) { %>disabled<% } %>>
                            <% if(!isModal() && isAdmin()) { %>
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default btn-sm">Speichern</button>
                                </span>
                            <% } %>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <a data-toggle="collapse" data-parent="<% if(isModal()) { %>#configGroupsSingle<% } else { %>#configGroupsGlobal<% } %>" href="<% if(isModal()) { %>#configGroupServicesSingle<% } else { %>#configGroupServicesGlobal<% } %>">Sensor-Dienste</a>
                </h4>
            </div>
            <div id="<% if(isModal()) { %>configGroupServicesSingle<% } else { %>configGroupServicesGlobal<% } %>" class="panel-collapse collapse">
                <div class="panel-body">
                    <p>Die nachfolgenden Dienste bestimmen, welche Ereignisse von Sensoren in welcher Ausf&uuml;hrlichkeit registriert werden k&ouml;nnen.</p>
                    <div class="panel panel-default panelRecon">
                        <div class="panel-heading">
                            <button type="button" class="toggle btn btn-primary btn-xs pull-right" data-toggle="button" data-inactive-text="Deaktiviert" data-active-text="Aktiviert">Deaktiviert</button>
                            recon
                        </div>
                        <div class="panel-body">
                            Hierbei handelt es sich um einen generischen Sammeldienst, der alle TCP- und UDP-Datenpakete aufzeichnet, die direkt an die IP-Adresse eines Sensors
                            gerichtet sind. Zum Schutz vor Denial-of-Service-Angriffen werden größere Datenmengen einer einzelnen Quelle oberhalb eines Schwellwertes geblockt.
                            Eine automatische Analyse aller von einer Quelle empfangenen Pakete kann zudem rudimentäre (Port-)Scans erkennen.
                        </div>
                    </div>
                    <div class="panel panel-default panelCowrie">
                        <div class="panel-heading">
                            <button type="button" class="toggle btn btn-primary btn-xs pull-right" data-toggle="button" data-inactive-text="Deaktiviert" data-active-text="Aktiviert">Deaktiviert</button>
                            cowrie
                        </div>
                        <div class="panel-body">
                            Ein Medium-Interaction-Honeypot, der das SSH-Protokoll simuliert. Zeichnet fehlgeschlagene Loginversuche mit verwendeten Benutzernamen und Passwörtern
                            auf und stellt Angreifern nach erfolgreichem Login eine simulierte Shell-Session bereit, in der alle Aktivitäten aufgezeichnet werden.
                        </div>
                    </div>
                    <div class="panel panel-default panelDionaea">
                        <div class="panel-heading">
                            <button type="button" class="toggle btn btn-primary btn-xs pull-right" data-toggle="button" data-inactive-text="Deaktiviert" data-active-text="Aktiviert">Deaktiviert</button>
                            dionaea
                        </div>
                        <div class="panel-body">
                            Low-Interaction-Honeypot, der bekannte Schwachstellen des unter Windows für Datei- und Druckerfreigaben verwendeten SMB-Protokolls simuliert.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <% if(isModal()) { %>
        <div class="form-group">
            <label class="control-label">Firmware</label>
            <select class="form-control sensorConfigImage">
                <option value="0">Systemstandard</option>
                <% _(images).each(function(i) { %>
                    <option value="<%- i.id %>" <% if(i.id == image) { %>selected<% } %>><%- i.name %> (<%- i.version %>)</option>
                <% }); %>
            </select>
        </div>
    <% } %>
</form>
<% if(isModal()) { %>
    <hr />
    <div class="form-group">
        <div class="btn-group btn-group-justified">
            <div class="btn-group">
                <button type="button" class="cancel btn btn-default">Abbrechen</button>
            </div>
            <div class="btn-group">
                <button type="button" class="save btn btn-primary">
                    <span class="glyphicon glyphicon-save"></span>&nbsp;&nbsp;Speichern
                </button>
            </div>
        </div>
    </div>
    </div>
    </div>
<% } %>