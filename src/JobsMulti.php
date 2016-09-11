<?php namespace JobApis\Jobs\Client;

use JobApis\Jobs\Client\Queries\AbstractQuery;

class JobsMulti
{
    /**
     * Job board API query objects
     *
     * @var AbstractQuery
     */
    protected $queries = [];

    /**
     * Creates query objects for each provider and creates this unified client.
     *
     * @param array $providers
     */
    public function __construct($providers = [])
    {
        foreach ($providers as $provider => $options) {
            $query = $provider.'Query';
            $className = 'JobApis\\Jobs\\Client\\Queries\\'.$query;
            $this->addQuery($provider, $className, $options);
        }
    }

    /**
     * Overrides get<Provider>Jobs() methods
     *
     * @param $method
     * @param $args
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        if ($this->isGetJobsByProviderMethod($method)) {
            return $this->getJobsByProvider($this->getProviderFromMethod($method));
        }

        throw new \BadMethodCallException(sprintf(
            '%s does not contain a method by the name of "%s"',
            __CLASS__,
            $method
        ));
    }

    /**
     * Instantiates a Query object and adds it to the queries array.
     *
     * @param $key string Query name
     * @param $className string Query class name
     * @param $options array Parameters to add to constructor
     *
     * @return $this
     */
    public function addQuery($key, $className, $options = [])
    {
        $this->queries[$key] = new $className($options);
        return $this;
    }

    /**
     * Gets jobs from all providers in a single go and returns an array of Collection objects.
     *
     * @return array
     */
    public function getAllJobs()
    {
        $jobs = [];
        foreach ($this->queries as $key => $query) {
            $jobs[$key] = $this->getJobsByProvider($key);
        }
        return $jobs;
    }

    /**
     * Gets jobs from a single provider and hydrates a new jobs collection.
     *
     * @return \JobApis\Jobs\Client\Collection
     */
    public function getJobsByProvider($provider)
    {
        $providerName = 'JobApis\\Jobs\\Client\\Providers\\'.$provider.'Provider';
        $client = new $providerName($this->queries[$provider]);
        return $client->getJobs();
    }

    /**
     * Sets a keyword on the query for each provider.
     *
     * @param $keyword string
     *
     * @return $this
     */
    public function setKeyword($keyword)
    {
        foreach ($this->queries as $provider => $query) {
            switch ($provider) {
                case 'Careerbuilder':
                    $query->set('Keywords', $keyword);
                    break;
                case 'Dice':
                    $query->set('text', $keyword);
                    break;
                case 'Govt':
                    $query->set('query', $keyword);
                    break;
                case 'Github':
                    $query->set('search', $keyword);
                    break;
                case 'Indeed':
                    $query->set('q', $keyword);
                    break;
                default:
                    throw new \Exception("Provider {$provider} not found");
            }
        }
        return $this;
    }

    /**
     * Sets a location on the query for each provider.
     *
     * @param $location
     *
     * @return $this
     */
    public function setLocation($location)
    {
        if (!$this->isValidLocation($location)) {
            throw new \OutOfBoundsException("Location parameter must follow the pattern 'City, ST'.");
        }

        $locationArr = explode(', ', $location);
        $city = $locationArr[0];
        $state = $locationArr[1];

        foreach ($this->queries as $provider => $query) {
            switch ($provider) {
                case 'Careerbuilder':
                    $query->set('UseFacets', 'true');
                    $query->set('FacetCityState', $location);
                    break;
                case 'Dice':
                    $query->set('city', $city);
                    $query->set('state', $state);
                    break;
                case 'Govt':
                    $queryString = $query->get('query').' in '.$location;
                    $query->set('query', $queryString);
                    break;
                case 'Github':
                    $query->set('location', $location);
                    break;
                case 'Indeed':
                    $query->set('l', $location);
                    break;
                default:
                    throw new \Exception("Provider {$provider} not found");
            }
        }
        return $this;
    }

    /**
     * Sets a page number and number of results per page for each provider.
     *
     * @param $page integer
     * @param $perPage integer
     *
     * @return $this
     */
    public function setPage($page = 1, $perPage = 10)
    {
        foreach ($this->queries as $provider => $query) {
            switch ($provider) {
                case 'Careerbuilder':
                    $query->set('PageNumber', $page);
                    $query->set('PerPage', $perPage);
                    break;
                case 'Dice':
                    $query->set('page', $page);
                    $query->set('pgcnt', $perPage);
                    break;
                case 'Govt':
                    $query->set('size', $perPage);
                    $query->set('from', $this->getStartFrom($page, $perPage));
                    break;
                case 'Github':
                    $query->set('page', $page-1);
                    // No per_page option
                    break;
                case 'Indeed':
                    $query->set('limit', $perPage);
                    $query->set('start', $this->getStartFrom($page, $perPage));
                    break;
                default:
                    throw new \Exception("Provider {$provider} not found");
            }
        }
        return $this;
    }

    /**
     * Gets a start from count for APIs that use per_page and start_from pattern.
     *
     * @param int $page
     * @param int $perPage
     *
     * @return int
     */
    private function getStartFrom($page = 1, $perPage = 10)
    {
        return ($page * $perPage) - $perPage;
    }

    /**
     * Tests whether location string follows valid convention (City, ST).
     *
     * @param string $location
     *
     * @return bool
     */
    private function isValidLocation($location = null)
    {
        preg_match("/([^,]+),\s*(\w{2})/", $location, $matches);
        return isset($matches[1]) && isset($matches[2]) ? true : false;
    }

    /**
     * Tests whether the method is a valid get<Provider>Jobs() method.
     *
     * @param $method
     *
     * @return bool
     */
    private function isGetJobsByProviderMethod($method)
    {
        return preg_match('/(get)(.*?)(Jobs)/', $method, $matches) && $matches[2] && isset($this->queries[$matches[2]]);
    }

    /**
     * Get the provider name from the method.
     *
     * @param $method
     *
     * @return string
     */
    private function getProviderFromMethod($method)
    {
        preg_match('/(get)(.*?)(Jobs)/', $method, $matches);
        return $matches[2];
    }
}
