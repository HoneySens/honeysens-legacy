<% if(_.templateHelpers.isAllowed('sensorconfigs', 'create')) { %>
<button id="addConfig" type="button" class="btn btn-default" <% if(!hasUnconfiguredSensors()) { %>disabled<% } %>>
    <span class="glyphicon glyphicon-plus"></span>&nbsp;&nbsp;Hinzuf&uuml;gen
</button>
<% } %>
<div class="row">
    <div class="col-sm-12 table-responsive">
        <table class="table table-striped">
            <thead>
            <th>Sensor</th>
            <th>Update-Intervall</th>
            <th>netfilter</th>
            <th>kippo</th>
            <th>dionaea</th>
            <% if(_.templateHelpers.isAllowed('sensorconfigs', 'update')) { %>
            <th>Aktionen</th>
            <% } %>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>