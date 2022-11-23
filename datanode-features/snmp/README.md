Workflow
========





Running scenarios all the times

external: sync targets

TargetConfig
------------
* capabilities
* credentialUuid

Target Events
-------------

* target_new:
* target_modified:
* target_removed:

Targets, Reachability
---------------------

SnmpTarget
  knownState (enum: new, ok, failing)
  errorMessage
  SocketAddress $address
  Credentials $credentials



SnmpTargets
- reachable
- unreachable
- newCandidates



ReachabilityChecker

 -> candidates
 -> reachable




Startup:

 * empty `SnmpScenarioRunner`


foreach (discoveryCandidates->getNext($limit) as SnmpTarget $target) {
  if (reachableCandidates->hasAddress($target->address)) {
    continue;
  }
  $this->scan($target)->then(function ($result) {
    /// TODO: depends on configuration. Always defined by central node? Then skip this
    reachableCandidates->add($target);
  }, function () {
    if ($target->knownState !== failing) {
      target->knownState = failing
    }
  });
}

foreach (reachableCandidates->getNext($limit) as SnmpTarget $target) {

}





----





Zentraler Knoten:

IcingaDataNode, startet mit Feature "Controller"


Namespaces
==========

Controller
----------
* Methods:
  * addNode()
  * deleteNode()
  * listConfiguredNodes()
  * listConnectedNodes()
  * getActiveConnections(): ConnectionInfo[]

CA
--
  * setAuthoritativeNode(NodeName $nodeName)
  * requestCertificate(CertificateSigningRequest)
  * listSignedCertificates()
  * getSignedCertificate(name)
* Router

* ~~Accept Config from~~


Feedback
--------

### Stadtwerke Bruneck

* Brauchen sie nicht, aber im Gespräch als Idee entstanden -> Tags aus Description, Hostname, whatever?
* find-Url -> host, port -> für einfache externe Verlinkung
* Berechtigungen auf Port-Ebene?
