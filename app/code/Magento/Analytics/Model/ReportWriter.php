<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Analytics\Model;

use Magento\Analytics\ReportXml\DB\ReportValidator;
use Magento\Framework\Filesystem\Directory\WriteInterface;

/**
 * Class ReportWriter
 *
 * @inheritdoc
 */
class ReportWriter implements ReportWriterInterface
{
    /**
     * File name for error reporting file in archive
     *
     * @var string
     */
    private $errorsFileName = 'errors.csv';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ProviderFactory
     */
    private $providerFactory;

    /**
     * @var ReportValidator
     */
    private $reportValidator;

    /**
     * ReportWriter constructor.
     *
     * @param ConfigInterface $config
     * @param ReportValidator $reportValidator
     * @param ProviderFactory $providerFactory
     */
    public function __construct(
        ConfigInterface $config,
        ReportValidator $reportValidator,
        ProviderFactory $providerFactory
    ) {
        $this->config = $config;
        $this->reportValidator = $reportValidator;
        $this->providerFactory = $providerFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function write(WriteInterface $directory, $path)
    {
        $directory->create($path);
        $errorsList = [];
        foreach($this->config->get() as $file) {
            foreach ($file['providers'] as $provider) {
                $error = $this->reportValidator->validate($provider[0]['name']);
                if ($error) {
                    $errorsList[] = $error;
                    continue;
                }

                $providerObject = $this->providerFactory->create($provider[0]['class']);
                $fileFullPath = $path . $provider[0]['name'] . '.csv';
                $fileData = $providerObject->getReport($provider[0]['name']);

                $directory->create($path);
                $stream = $directory->openFile($fileFullPath, 'w+');
                $stream->lock();
                foreach ($fileData as $row) {
                    $stream->writeCsv($row);
                }
                $stream->unlock();
                $stream->close();
            }
        }
        if ($errorsList) {
            $errorStream = $directory->openFile($path . $this->errorsFileName, 'w+');
            foreach ($errorsList as $error) {
                $errorStream->lock();
                $errorStream->writeCsv($error);
                $errorStream->unlock();
            }
            $errorStream->close();
        }
    }
}
