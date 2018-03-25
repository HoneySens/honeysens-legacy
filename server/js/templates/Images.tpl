<h1 class="page-header"><span class="glyphicon glyphicon-save"></span>&nbsp;Firmware</h1>
<% if(_.templateHelpers.isAllowed('sensorimages', 'create')) { %>
<button id="addImage" type="button" class="btn btn-default"><span class="glyphicon glyphicon-plus"></span>&nbsp;&nbsp;Hinzuf&uuml;gen</button>
<% } %>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <th>Name</th>
            <th>Version</th>
            <th>Beschreibung</th>
            <% if(_.templateHelpers.isAllowed('sensorimages', 'update')) { %><th>Aktionen</th><% } %>
        </thead>
        <tbody></tbody>
    </table>
</div>