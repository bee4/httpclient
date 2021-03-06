<?php
/**
 * This file is part of the bee4/transport package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Bee4 2015
 * @author  Stephane HULARD <s.hulard@chstudio.fr>
 * @package Bee4\Transport\Handle
 */

namespace Bee4\Transport\Handle;

use Composer\CaBundle\CaBundle;
use Bee4\Transport\Message\Request\AbstractRequest;
use Bee4\Transport\Configuration;
use Bee4\Transport\Exception\Curl\ExceptionFactory;
use Bee4\Transport\Exception\RuntimeException;

/**
 * Define cURL handle wrapper
 * @package Bee4\Transport\Handle
 */
class CurlHandle implements HandleInterface
{
    /**
     * cURL resource handle
     * @var resource
     */
    private $handle;

    /**
     * Option collection used for the current request
     * @var array
     */
    private $options = [];

    /**
     * Initialize cURL resource
     */
    public function __construct()
    {
        // @codeCoverageIgnoreStart
        if (!extension_loaded('curl')) {
            throw new RuntimeException('The PHP cURL extension must be installed!');
        }
        // @codeCoverageIgnoreEnd

        $this->setDefaults();
        $this->open();
    }

    /**
     * Set default CURL options
     */
    private function setDefaults()
    {
        $this->options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => true,
            CURLINFO_HEADER_OUT => true
        ];
    }

    /**
     * Handle destructor
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Open the curl handle to be used
     * @return Handle
     */
    public function open()
    {
        if (!is_resource($this->handle)) {
            $this->handle = curl_init();
        }
        return $this;
    }

    /**
     * Close currently opened handle
     * @return Handle
     */
    public function close()
    {
        if (is_resource($this->handle)) {
            curl_close($this->handle);
        }
        $this->handle = null;
        return $this;
    }

    /**
     * Prepare the handle to be configured by a given request
     * @param  AbstractRequest $request
     * @return CurlHandle
     */
    public function prepare(AbstractRequest $request)
    {
        $config = $request->getOptions();

        $this->options[CURLOPT_URL] = (string)$config->url;
        $this->options[CURLOPT_UPLOAD] = (bool)$config->upload;
        $this->options[CURLOPT_HTTPHEADER] = $request->getHeaderLines();

        if ($config instanceof Configuration\HttpConfiguration) {
            $this->prepareHttp($config);
        }
        if ($config instanceof Configuration\FtpConfiguration) {
            $this->options[CURLOPT_FTP_USE_EPSV] = $config->passive;
            $this->options[CURLOPT_QUOTE] = $config->commandsRequest();
            $this->options[CURLOPT_POSTQUOTE] = $config->commandsPost();
        }
        if ($config instanceof Configuration\SshConfiguration) {
            $this->options[CURLOPT_POSTQUOTE] = $config->commandsPost();
        }

        if ($config->hasBody()) {
            $body = $config->body;
            if (is_resource($body)) {
                $this->options[CURLOPT_INFILE] = $body;
                $md = stream_get_meta_data($body);
                $this->options[CURLOPT_INFILESIZE] = filesize($md['uri']);
            } else {
                $this->options[CURLOPT_POSTFIELDS] = $body;
            }
        } else {
            $this->options[CURLOPT_NOBODY] = true;
        }

        return $this;
    }

    /**
     * Specific method to prepare HTTP requests options
     * @param Configuration\HttpConfiguration $config
     */
    private function prepareHttp(Configuration\HttpConfiguration $config)
    {
        switch ($config->method) {
            case 'GET':
                $this->options[CURLOPT_HTTPGET] = true;
                break;
            case 'PUT':
                if (is_resource($config->body)) {
                    $this->options[CURLOPT_PUT] = true;
                } else {
                    $this->options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                }
                break;
            default:
                $this->options[CURLOPT_CUSTOMREQUEST] = $config->method;
        }

        if ($config->redirectsAllowed()) {
            $this->options[CURLOPT_AUTOREFERER] = $config->allowRedirectsReferer();
            $this->options[CURLOPT_MAXREDIRS] = $config->allowRedirectsMax();
        } else {
            $this->options[CURLOPT_FOLLOWLOCATION] = false;
        }

        if (null !== $config->accept_encoding) {
            $this->options[CURLOPT_ENCODING] = $config->accept_encoding;
        }

        if (true === $config->verify) {
            $this->options[CURLOPT_SSL_VERIFYPEER] = true;
            $this->options[CURLOPT_SSL_VERIFYHOST] = 2;
            $this->options[CURLOPT_CAINFO] = CaBundle::getSystemCaRootBundlePath();
        } else {
            $this->options[CURLOPT_SSL_VERIFYPEER] = false;
            $this->options[CURLOPT_SSL_VERIFYHOST] = 0;
        }
    }

    /**
     * Execute current handle and return result
     * @throws RuntimeException
     * @throws CurlException
     * @return string
     */
    public function execute()
    {
        if (!is_resource($this->handle)) {
            throw new RuntimeException('Curl handle has been closed, just open it before execute...');
        }

        curl_setopt_array($this->handle, array_filter($this->options));
        $return = curl_exec($this->handle);

        if ($return === false) {
            throw ExceptionFactory::build(
                curl_errno($this->handle),
                curl_error($this->handle)
            );
        }

        return $return;
    }

    /**
     * Check PHP version and reset handle option if possible
     * @return boolean
     */
    public function reset()
    {
        if (is_resource($this->handle)) {
            curl_reset($this->handle);
            $this->setDefaults();
            return true;
        }

        return false;
    }

    /**
     * Access to `curl_getinfo` result on the current handle
     * @return array
     */
    public function getCurlInfo()
    {
        return curl_getinfo($this->handle);
    }

    /**
     * Retrieve ExecutionInfos details
     * @return ExecutionInfos
     */
    public function infos()
    {
        return (new ExecutionInfos($this))
            ->status(curl_getinfo($this->handle, CURLINFO_HTTP_CODE))
            ->headers(curl_getinfo($this->handle, CURLINFO_HEADER_OUT))
            ->effectiveUrl(curl_getinfo($this->handle, CURLINFO_EFFECTIVE_URL))
            ->transactionTime(curl_getinfo($this->handle, CURLINFO_TOTAL_TIME));
    }
}
