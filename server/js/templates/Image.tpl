<td><%- name %></td>
<td><%- version %></td>
<td><%- description %></td>
<% if(_.templateHelpers.isAllowed('sensorimages', 'update') || _.templateHelpers.isAllowed('sensorimages', 'download')) { %>
<td>
    <% if(_.templateHelpers.isAllowed('sensorimages', 'download')) { %>
        <a class="btn btn-default btn-xs" href="api/sensorimages/download/<%- id %>" data-toggle="tooltip" title="Download">
            <span class="glyphicon glyphicon-download-alt"></span>
        </a>
    <% } %>
    <% if(_.templateHelpers.isAllowed('sensorimages', 'update')) { %>
        <% if(!isDefault()) { %>
            <button type="button" class="updateToImage btn btn-default btn-xs" data-toggle="tooltip" title="Als Standardfirmware">
                <span class="glyphicon glyphicon-arrow-up"></span>
            </button>
        <% } %>
        <% if(!isInUse()) { %>
            <button type="button" class="removeImage btn btn-default btn-xs" data-toggle="tooltip" title="Entfernen">
                <span class="glyphicon glyphicon-remove"></span>
            </button>
        <% } %>
    <% } %>
</td>
<% } %>