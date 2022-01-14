<?php

declare(strict_types=1);

namespace AdyenPayment\Certificate\Filesystem;

use AdyenPayment\Certificate\Exception\CouldNotWriteCertificate;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

final class CertificateWriter implements CertificateWriterInterface
{
    public const APPLE_PAY_CERTIFICATE_DIR = '.well-known';
    public const APPLE_PAY_CERTIFICATE = 'apple-developer-merchantid-domain-association';
    private const APPLE_PAY_CERTIFICATE_FILE_PATH = self::APPLE_PAY_CERTIFICATE_DIR.'/'.self::APPLE_PAY_CERTIFICATE;
    public const ADYEN_APPLE_PAY_ZIP_EXTENSION = '.zip';

    public function __invoke(string $content): void
    {
        $filesystem = new Filesystem();

        try {
            $filesystem->mkdir(self::APPLE_PAY_CERTIFICATE_DIR, 0700);
            $filesystem->dumpFile(
                self::APPLE_PAY_CERTIFICATE_FILE_PATH,
                $content
            );
        } catch (IOExceptionInterface $exception) {
            throw CouldNotWriteCertificate::withFilepath(self::APPLE_PAY_CERTIFICATE_FILE_PATH, $exception);
        }
    }
}
