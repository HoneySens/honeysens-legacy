<div class="row addForm">
    <div class="col-sm-12">
        <h1 class="page-header"><span class="glyphicon glyphicon-plus"></span>&nbsp;Sensor <% if(isNew()) { %>hinzuf&uuml;gen<% } else { %>bearbeiten<% } %></h1>
        <form>
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label for="sensorName" class="control-label">Name</label>
                        <input type="text" name="sensorName" class="form-control" placeholder="Sensorname" value="<%- name %>" />
                    </div>
                    <div class="form-group">
                        <label for="location" class="control-label">Standort</label>
                        <input type="text" name="location" class="form-control" placeholder="z.B. Raum 312" value="<%- location %>" />
                    </div>
                    <div class="form-group">
                        <label for="division" class="control-label">Gruppe</label>
                        <select class="form-control" name="division">
                            <% _(divisions).each(function(d) { %>
                                <option value="<%- d.id %>"><%- d.name %></option>
                            <% }); %>
                        </select>
                    </div>
                    <fieldset>
                        <legend>Erreichbarkeit HoneySens-Server</legend>
                        <div class="form-group">
                            <div class="btn-group btn-group-justified" data-toggle="buttons">
                                <label class="btn btn-default">
                                    <input type="radio" name="serverEndpoint" value="0">Standard</input>
                                </label>
                                <label class="btn btn-default">
                                    <input type="radio" name="serverEndpoint" value="1">Individuell</input>
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="serverHost" class="control-label">Host</label>
                            <input type="text" name="serverHost" class="form-control" placeholder="IP-Adresse des Servers" />
                        </div>
                        <div class="form-group">
                            <label for="serverPortHTTPS" class="control-label">HTTPS-Port (API)</label>
                            <input type="text" name="serverPortHTTPS" class="form-control" placeholder="Standard: 443" />
                        </div>
                    </fieldset>
                </div>
                <div class="col-sm-6">
                    <fieldset>
                        <legend>Netzwerkschnittstelle</legend>
                        <div class="form-group">
                            <div class="btn-group btn-group-justified" data-toggle="buttons">
                                <label class="btn btn-default">
                                    <input type="radio" name="networkMode" value="0">DHCP</input>
                                </label>
                                <label class="btn btn-default">
                                    <input type="radio" name="networkMode" value="1">Statisch</input>
                                </label>
                            </div>
                        </div>
                        <div class="form-group networkModeDHCP">
                            <p class="form-control-static">IP-Adresse und Subnetzmaske werden automatisch vom DHCP-Server bezogen.</p>
                        </div>
                        <div class="form-group networkModeStatic">
                            <label for="networkIP" class="control-label">IP-Adresse</label>
                            <input type="text" name="networkIP" class="form-control" placeholder="z.B. 192.168.1.13" />
                        </div>
                        <div class="form-group networkModeStatic">
                            <label for="networkNetmask" class="control-label">Subnetzmaske</label>
                            <input type="text" name="networkNetmask" class="form-control" placeholder="z.B. 255.255.255.0" />
                        </div>
                        <div class="form-group networkModeStatic">
                            <label for="networkGateway" class="control-label">Gateway</label>
                            <input type="text" name="networkGateway" class="form-control" placeholder="optional" />
                        </div>
                        <div class="form-group networkModeStatic">
                            <label for="networkDNS" class="control-label">DNS-Server</label>
                            <input type="text" name="networkDNS" class="form-control" placeholder="optional" />
                        </div>
                        <div class="form-group">
                            <label for="networkMACMode" class="control-label">MAC-Adresse</label>
                            <div class="btn-group btn-group-justified" data-toggle="buttons">
                                <label class="btn btn-default">
                                    <input type="radio" name="networkMACMode" value="0">Standard</input>
                                </label>
                                <label class="btn btn-default">
                                    <input type="radio" name="networkMACMode" value="1">Individuell</input>
                                </label>
                            </div>
                        </div>
                        <div class="form-group networkMACOriginal">
                            <p class="form-control-static">Es wird die originale MAC-Adresse des verbauten Netzwerkinterfaces genutzt.</p>
                        </div>
                        <div class="form-group networkMACCustom">
                            <label for="customMAC" class="control-label">Individuelle MAC-Adresse</label>
                            <input type="text" name="customMAC" class="form-control" placeholder="00:11:22:33:44:55" />
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend>HTTP(S)-Proxy</legend>
                        <div class="form-group">
                            <div class="btn-group btn-group-justified" data-toggle="buttons">
                                <label class="btn btn-default">
                                    <input type="radio" name="proxyType" value="0">Inaktiv</input>
                                </label>
                                <label class="btn btn-default">
                                    <input type="radio" name="proxyType" value="1">Aktiv</input>
                                </label>
                            </div>
                        </div>
                        <div class="form-group proxyTypeDisabled">
                            <p class="form-control-static">Es kommt kein Proxy-Server zum Einsatz.</p>
                        </div>
                        <div class="form-group proxyTypeEnabled">
                            <label for="proxyHost" class="control-label">Proxy-Server</label>
                            <input type="text" name="proxyHost" class="form-control" placeholder="z.B. 10.0.0.3" />
                        </div>
                        <div class="form-group proxyTypeEnabled">
                            <label for="proxyPort" class="control-label">Port</label>
                            <input type="text" name="proxyPort" class="form-control" placeholder="z.B. 3128" />
                        </div>
                        <div class="form-group proxyTypeEnabled">
                            <label for="proxyUser" class="control-label">Benutzer</label>
                            <input type="text" name="proxyUser" class="form-control" placeholder="optional" />
                        </div>
                        <div class="form-group proxyTypeEnabled">
                            <label for="proxyPassword" class="control-label">Passwort</label>
                            <input type="text" name="proxyPassword" class="form-control" placeholder="optional" />
                        </div>
                    </fieldset>
                </div>
            </div>
            <hr />
            <div class="form-group">
                <div class="btn-group btn-group-justified">
                    <div class="btn-group">
                        <button type="button" class="cancel btn btn-default">&nbsp;&nbsp;Abbrechen</button>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-save"></span>&nbsp;&nbsp;Speichern</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="row addBusy hide">
    <div class="col-sm-12">
        <p class="text-center">Daten werden verarbeitet</p>
        <div class="loading center-block"></div>
    </div>
</div>
<div class="row addResult hide">
    <div class="col-sm-12">
        <div class="resultSuccess">
            <div class="alert alert-success">Der neue Sensor wurde erfolgreich auf dem Server registriert</div>
            <p>Laden Sie nun im nächsten Schritt die Sensor-Firmware und die individuelle Sensor-Konfiguration herunter.
                Schreiben Sie anschließend die Firmware auf eine SD-Karte und kopieren das Konfigurationsarchiv auf deren
                erste Partition. Schließen Sie zuletzt den Sensor mit eingesteckter SD-Karte an das Netzwerk an.</p>
            <div class="row">
                <div class="col-sm-6 firmware">
                    <a class="btn btn-primary btn-block"><span class="glyphicon glyphicon-download"></span>&nbsp;&nbsp;Firmware-Download</a>
                </div>
                <div class="col-sm-6 configArchive">
                    <h5 class="text-center"><strong>Bitte warten, Konfiguration wird erzeugt...</strong></h5>
                    <a class="btn btn-primary btn-block hide"><span class="glyphicon glyphicon-download"></span>&nbsp;&nbsp;Sensor-Konfiguration</a>
                </div>
            </div>
        </div>
        <div class="resultError hide">
            <div class="alert alert-danger">Es ist ein Fehler aufgetreten.</div>
        </div>
        <hr />
        <button type="button" class="cancel btn btn-default btn-block">Schlie&szlig;en</button>
    </div>
</div>
