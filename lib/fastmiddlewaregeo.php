<?php
namespace Octo;

use Geocoder\Geocoder;
use Geocoder\Provider\FreeGeoIp;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Ivory\HttpAdapter\FopenHttpAdapter;
use Psr\Http\Message\ServerRequestInterface;

class Fastmiddlewaregeo extends FastMiddleware
{
    /**
     * @var Geocoder
     */
    private $geocoder;

    /**
     * @var string|null
     */
    private $ipAttribute;

    /**
     * @var string The attribute name
     */
    private $attribute = 'geolocation';

    /**
     * Constructor. Set the geocoder instance.
     *
     * @param null|Geocoder $geocoder
     */
    public function __construct(Geocoder $geocoder = null)
    {
        $this->geocoder = $geocoder;
    }

    /**
     * Set the attribute name to get the client ip.
     *
     * @param string $ipAttribute
     *
     * @return self
     */
    public function ipAttribute($ipAttribute)
    {
        $this->ipAttribute = $ipAttribute;

        return $this;
    }

    /**
     * Set the attribute name to store the geolocation info.
     *
     * @param string $attribute
     *
     * @return self
     */
    public function attribute($attribute)
    {
        $this->attribute = $attribute;

        return $this;
    }

    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface $next
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $next)
    {
        $ip = $this->getIp($request);

        if (!empty($ip)) {
            $geocoder = $this->geocoder ?: self::createGeocoder();
            $address = $geocoder->geocode($ip);
            $request = $request->withAttribute($this->attribute, $address);
        }

        return $next->process($request);
    }

    /**
     * Get the client ip.
     *
     * @param ServerRequestInterface $request
     *
     * @return string
     */
    private function getIp(ServerRequestInterface $request)
    {
        $server = $request->getServerParams();

        if ($this->ipAttribute !== null) {
            return $request->getAttribute($this->ipAttribute);
        }

        return isset($server['REMOTE_ADDR']) ? $server['REMOTE_ADDR'] : '';
    }

    /**
     * Generate a default geocoder.
     *
     * @return Geocoder
     */
    public static function createGeocoder()
    {
        return new FreeGeoIp(new FopenHttpAdapter());
    }
}
