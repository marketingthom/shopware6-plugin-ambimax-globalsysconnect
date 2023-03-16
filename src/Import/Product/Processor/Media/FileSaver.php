<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Import\Product\Processor\Media;


use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class FileSaver
{
    private \Shopware\Core\Content\Media\File\FileSaver $fileSaver;
    private FileNameProvider $fileNameProvider;

    public function __construct(
        \Shopware\Core\Content\Media\File\FileSaver $fileSaver,
        FileNameProvider                            $fileNameProvider
    )
    {
        $this->fileSaver = $fileSaver;
        $this->fileNameProvider = $fileNameProvider;
    }

    public function persistFileToMedia(MediaFile $mediaFile, string $fileName, ?string $mediaId): string
    {
        if (!$mediaId) {
            $mediaId = Uuid::randomHex();
        }

        $this->fileSaver->persistFileToMedia(
            $mediaFile,
            $this->fileNameProvider->provide(
                $fileName,
                $mediaFile->getFileExtension(),
                $mediaId,
                new Context(new SystemSource())
            ),
            $mediaId,
            new Context(new SystemSource())
        );

        return $mediaId;
    }
}
