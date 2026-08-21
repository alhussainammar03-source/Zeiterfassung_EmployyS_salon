<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Api\Exception\ApiError;

/**
 * Kapselt den Upload von Mitarbeiterfotos zu Cloudinary.
 *
 * Nutzung:
 *   $uploader = new CloudinaryUploader();
 *   $url = $uploader->uploadEmployeePhoto($_FILES['photo']['tmp_name'], $employeeId);
 */
class CloudinaryUploader
{
    private UploadApi $uploadApi;
    private string $uploadFolder;

    public function __construct()
    {
        $config = require __DIR__ . '/../config/cloudinary_config.php';

        Configuration::instance([
            'cloud' => [
                'cloud_name' => $config['cloud_name'],
                'api_key' => $config['api_key'],
                'api_secret' => $config['api_secret'],
            ],
            'url' => ['secure' => true],
        ]);

        $this->uploadApi = new UploadApi();
        $this->uploadFolder = $config['upload_folder'];
    }

    /**
     * Lädt ein Mitarbeiterfoto hoch und gibt die öffentliche HTTPS-URL zurück.
     *
     * @param string $tmpFilePath Temporärer Pfad aus $_FILES['photo']['tmp_name']
     * @param int    $employeeId  Mitarbeiter-ID, wird Teil der public_id
     *                            (sorgt dafür, dass ein erneuter Upload
     *                            das alte Foto in Cloudinary überschreibt)
     *
     * @throws RuntimeException wenn der Upload fehlschlägt
     */
    public function uploadEmployeePhoto(string $tmpFilePath, int $employeeId): string
    {
        try {
            $result = $this->uploadApi->upload($tmpFilePath, [
                'folder' => $this->uploadFolder,
                'public_id' => 'employee_' . $employeeId,
                'overwrite' => true,
                'resource_type' => 'image',
            ]);
        } catch (ApiError $exception) {
            throw new RuntimeException(
                'Foto-Upload zu Cloudinary fehlgeschlagen: ' . $exception->getMessage()
            );
        }

        if (!isset($result['secure_url'])) {
            throw new RuntimeException('Cloudinary hat keine URL zurückgegeben.');
        }

        return $result['secure_url'];
    }

    /**
     * Lädt ein Profilfoto für einen Kunden hoch und gibt die
     * öffentliche HTTPS-URL zurück.
     *
     * @throws RuntimeException wenn der Upload fehlschlägt
     */
    public function uploadCustomerPhoto(string $tmpFilePath, int $customerId): string
    {
        try {
            $result = $this->uploadApi->upload($tmpFilePath, [
                'folder' => $this->uploadFolder . '/customers',
                'public_id' => 'customer_' . $customerId,
                'overwrite' => true,
                'resource_type' => 'image',
            ]);
        } catch (ApiError $exception) {
            throw new RuntimeException(
                'Foto-Upload zu Cloudinary fehlgeschlagen: ' . $exception->getMessage()
            );
        }

        if (!isset($result['secure_url'])) {
            throw new RuntimeException('Cloudinary hat keine URL zurückgegeben.');
        }

        return $result['secure_url'];
    }

    /**
     * Lädt ein Foto für einen Salon-News-Beitrag hoch.
     *
     * @throws RuntimeException wenn der Upload fehlschlägt
     */
    public function uploadNewsPhoto(string $tmpFilePath, int $newsId): string
    {
        try {
            $result = $this->uploadApi->upload($tmpFilePath, [
                'folder' => $this->uploadFolder . '/news',
                'public_id' => 'news_' . $newsId,
                'overwrite' => true,
                'resource_type' => 'image',
            ]);
        } catch (ApiError $exception) {
            throw new RuntimeException(
                'Foto-Upload zu Cloudinary fehlgeschlagen: ' . $exception->getMessage()
            );
        }

        if (!isset($result['secure_url'])) {
            throw new RuntimeException('Cloudinary hat keine URL zurückgegeben.');
        }

        return $result['secure_url'];
    }

    /**
     * Lädt ein Foto für eine Dienstleistung hoch und gibt die
     * öffentliche HTTPS-URL zurück.
     *
     * @throws RuntimeException wenn der Upload fehlschlägt
     */
    public function uploadServicePhoto(string $tmpFilePath, int $serviceId): string
    {
        try {
            $result = $this->uploadApi->upload($tmpFilePath, [
                'folder' => $this->uploadFolder . '/services',
                'public_id' => 'service_' . $serviceId,
                'overwrite' => true,
                'resource_type' => 'image',
            ]);
        } catch (ApiError $exception) {
            throw new RuntimeException(
                'Foto-Upload zu Cloudinary fehlgeschlagen: ' . $exception->getMessage()
            );
        }

        if (!isset($result['secure_url'])) {
            throw new RuntimeException('Cloudinary hat keine URL zurückgegeben.');
        }

        return $result['secure_url'];
    }

    /**
     * Lädt eine AU-Bescheinigung (Bild oder PDF) hoch und gibt die
     * öffentliche HTTPS-URL zurück. Nutzt resource_type "auto", damit
     * sowohl Bilder als auch PDFs korrekt verarbeitet werden.
     *
     * @throws RuntimeException wenn der Upload fehlschlägt
     */
    public function uploadKrankmeldung(string $tmpFilePath, int $employeeId): string
    {
        try {
            $result = $this->uploadApi->upload($tmpFilePath, [
                'folder' => $this->uploadFolder . '/krankmeldungen',
                'public_id' => 'krank_' . $employeeId . '_' . time(),
                'resource_type' => 'auto',
            ]);
        } catch (ApiError $exception) {
            throw new RuntimeException(
                'Datei-Upload zu Cloudinary fehlgeschlagen: ' . $exception->getMessage()
            );
        }

        if (!isset($result['secure_url'])) {
            throw new RuntimeException('Cloudinary hat keine URL zurückgegeben.');
        }

        return $result['secure_url'];
    }
}
