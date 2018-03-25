Rough outline of how to programmatically delete a repository with docker-registry-util
~~~
from docker_registry_util.client import DockerRegistryClient
from docker_registry_util.query import DockerRegistryQuery
from docker_registry_util.remover import DockerRegistryRemover

client = DockerRegistryClient('http://honeysens-registry:5000')
query = DockerRegistryQuery(client)
remover = DockerRegistryRemover(query)
remover.remove_repositories('honeysens/cowrie')
~~~


* Visualize service upload process (esp. the job processing/registry upload)
  -> it might be sensible to look for a beanstalk replacement that allows us
     to track job progress
* Deletion of service tags and whole repositories
* Default revision selection for services
* Richer service XML (website, short and long description, logo)
