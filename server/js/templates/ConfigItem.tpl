<td><%- showSensor() %></td>
<td><%- interval %> min</td>
<td><% if(recon) { %><span class="glyphicon glyphicon-ok"></span><% } else { %><span class="glyphicon glyphicon-remove"></span><% } %></td>
<td><% if(kippoHoneypot) { %><span class="glyphicon glyphicon-ok"></span><% } else { %><span class="glyphicon glyphicon-remove"></span><% } %></td>
<td><% if(dionaeaHoneypot) { %><span class="glyphicon glyphicon-ok"></span><% } else { %><span class="glyphicon glyphicon-remove"></span><% } %></td>
<% if(_.templateHelpers.isAllowed('sensorconfigs', 'update')) { %>
<td>
    <button type="button" class="editSensorConfig btn btn-default btn-xs" data-toggle="tooltip" title="Bearbeiten">
        <span class="glyphicon glyphicon-pencil"></span>
    </button>
    <button type="button" class="removeSensorConfig btn btn-default btn-xs" data-toggle="tooltip" title="Entfernen">
        <span class="glyphicon glyphicon-remove"></span>
    </button>
</td>
<% } %>