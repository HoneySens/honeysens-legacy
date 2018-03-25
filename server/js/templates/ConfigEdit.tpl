<% if(isModal()) { %>
<div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h4>Spezifische Konfiguration <% if(isEdit()) { %>bearbeiten<% } else { %>hinzuf&uuml;gen<% } %></h4>
        </div>
        <div class="modal-body">
            <% } %>
            <form class="form-horizontal" role="form">
                <% if(isModal()) { %>
                <div class="form-group">
                    <label for="sensorConfigSensor" class="col-sm-3 control-label">Sensor</label>
                    <div class="col-sm-5">
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
                </div>
                <% } %>
                <% if(!isModal()) { %><div class="row"><% } %>
                    <% if(!isModal()) { %><div class="col-lg-5"><% } %>
                        <div class="form-group">
                            <% if(!isModal()) { %>
                            <p class="col-sm-12">Das Updateintervall gibt an, in welchen zeitlichen Abst&auml;nden die Sensoren den HoneySens-Server kontaktieren sollen,
                                um Statusmeldungen abzuliefern und ihre aktuelle Konfiguration oder auch Firmwareupdates zu empfangen.</p>
                            <% } %>
                            <label for="sensorConfigInterval" class="<% if(isModal()) { %> col-sm-3 <% } else { %> col-sm-6 <% } %> control-label">Updateintervall (min.)</label>
                            <div class="<% if(isModal()) { %> col-sm-3 <% } else { %> col-sm-3 <% } %>">
                                <input type="text" class="form-control sensorConfigInterval" name="sensorConfigInterval" value="<%- interval %>" <% if(!_.templateHelpers.isAllowed('sensorconfigs', 'update')) { %>disabled<% } %>>
                            </div>
                        </div>
                        <% if(!isModal()) { %></div><% } %>
                    <% if(!isModal()) { %><div class="col-lg-4"><% } %>
                        <div class="form-group">
                            <label class="<% if(isModal()) { %> col-sm-3 <% } else { %> col-lg-4 col-sm-5 <% } %> control-label">Aktive Dienste</label>
                            <div class="col-lg-8 col-sm-7">
                                <div class="checkbox <% if(!_.templateHelpers.isAllowed('sensorconfigs', 'update')) { %>disabled<% } %>">
                                    <label>
                                        <input type="checkbox" class="sensorConfigRecon" <% if(recon) { %>checked<% } %> <% if(!_.templateHelpers.isAllowed('sensorconfigs', 'update')) { %>disabled<% } %>>
                                        Recon
                                    </label>
                                </div>
                                <div class="checkbox <% if(!_.templateHelpers.isAllowed('sensorconfigs', 'update')) { %>disabled<% } %>">
                                    <label>
                                        <input type="checkbox" class="sensorConfigKippoHoneypot" <% if(kippoHoneypot) { %>checked<% } %> <% if(!_.templateHelpers.isAllowed('sensorconfigs', 'update')) { %>disabled<% } %>>
                                        SSH (kippo)
                                    </label>
                                </div>
                                <div class="checkbox <% if(!_.templateHelpers.isAllowed('sensorconfigs', 'update')) { %>disabled<% } %>">
                                    <label>
                                        <input type="checkbox" class="sensorConfigDionaeaHoneypot" <% if(dionaeaHoneypot) { %>checked<% } %> <% if(!_.templateHelpers.isAllowed('sensorconfigs', 'update')) { %>disabled<% } %>>
                                        dionaea
                                    </label>
                                </div>
                            </div>
                        </div>
                        <% if(isModal()) { %>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Firmware</label>
                            <div class="col-sm-7">
                                <select class="form-control sensorConfigImage">
                                    <option value="0">Systemstandard</option>
                                    <% _(images).each(function(i) { %>
                                    <option value="<%- i.id %>" <% if(i.id == image) { %>selected<% } %>><%- i.name %> (<%- i.version %>)</option>
                                    <% }); %>
                                </select>
                            </div>
                        </div>
                        <% } %>
                        <% if(!isModal()) { %></div><% } %>
                    <% if(!isModal()) { %></div><% } %>
            </form>
            <% if(isModal()) { %>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Abbrechen</button>
            <button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-save"></span>&nbsp;&nbsp;Speichern</button>
        </div>
    </div>
</div>
<% } else { %>
<% if(_.templateHelpers.isAllowed('sensorconfigs', 'update')) { %>
<div class="row">
<div class="col-sm-12">
<button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-save"></span>&nbsp;&nbsp;&Auml;nderungen speichern</button>
</div>
</div>
<% } %>
<% } %>