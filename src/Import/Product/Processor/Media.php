<?php declare(strict_types=1);


namespace Ambimax\GlobalsysConnect\Import\Product\Processor;


use Ambimax\GlobalsysConnect\Import\Product\Processor\Media\FileSaver;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaFolderRepositoryDecorator;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaRepositoryDecorator;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class Media extends AbstractProcessor implements ProcessorInterface
{
    const MIME_TYPE = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];

    protected EntityRepositoryInterface $productRepository;
    protected FileSaver $fileSaver;
    protected EntityRepository $mediaFolderRepository;
    protected EntityRepository $mediaRepository;

    public function __construct(
        EntityRepositoryInterface      $productRepository,
        EntityRepository       $mediaRepository,
        FileSaver                      $fileSaver,
        EntityRepository $mediaFolderRepository
    )
    {
        $this->productRepository = $productRepository;
        $this->mediaRepository = $mediaRepository;
        $this->fileSaver = $fileSaver;
        $this->mediaFolderRepository = $mediaFolderRepository;
    }

    public function provide(?array $productData = null): array
    {
        if (empty($productData) || empty($productData['pictures'])) {
            return [];
        }

        $productEntity = $this->loadProductEntityWithProductMedia($productData['products_sku']);

        if (!$productEntity) {
            return $this->generateMedia($productData);
        }

        $productMediaCollection = $productEntity->getMedia();

        if (!$productMediaCollection || !$productMediaCollection->count()) {
            return $this->generateMedia($productData);
        }

        return [];
    }

    protected function loadProductEntityWithProductMedia($productNumber): ?ProductEntity
    {
        $productCollection = $this->productRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('productNumber', $productNumber))
                ->addAssociation('media'),
            new Context(new SystemSource())
        );

        if (!$productCollection->count()) {
            return null;
        }

        return $productCollection->first();
    }

    /**
     * @param array $productData
     * @param string|null $folderId
     * @return array
     */
    protected function generateMedia(array $productData, string $folderId = null): array
    {
        $media = [];
        if (!$folderId) {
            $folderId = $this->getProductMediaFolderId();
        }

        $position = 1;
        foreach ($productData['pictures']['picture'] as $imageUrl) {

            list($fileName, $fileExtension) = $this->parseUrlToFileData($imageUrl);

            $mediaId = $this->upsertMedia($fileName, $fileExtension, null, $folderId);

            $this->fileSaver->persistFileToMedia(
                new MediaFile(
                    $imageUrl,
                    $this->getMimeType($fileExtension),
                    $fileExtension,
                    0
                ),
                $fileName,
                $mediaId
            );

            $media[] = [
                'mediaId'  => $mediaId,
                'position' => $position++
            ];
        }

        return $media;
    }

    protected function parseUrlToFileData($imageUrl): array
    {
        $explodeUrl = explode('/', $imageUrl);
        $fileName = explode('.', array_pop($explodeUrl));
        $fileExtension = $fileName[1];
        $fileName = $fileName[0];
        return [$fileName, $fileExtension];
    }

    protected function getProductMediaFolderId(): ?string
    {
        return $this->mediaFolderRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('name', 'Product Media')),
            new Context(new SystemSource())
        )->firstId();
    }

    protected function upsertMedia(string $fileName, string $fileExtension, ?string $mediaId, ?string $folderId): string
    {
        $medium = [
            'id'            => $mediaId ?: Uuid::randomHex(),
            'mediaFolderId' => $folderId,
            'name'          => $fileName,
            'fileName'      => $fileName,
            'alt'           => $fileName,
            'mimeType'      => $this->getMimeType($fileExtension),
            'fileExtension' => $fileExtension,
        ];

        $this->mediaRepository->upsert([$medium], new Context(new SystemSource()));

        return $medium['id'];
    }

    public function getMimeType(string $fileExtension): string
    {
        return self::MIME_TYPE[strtolower($fileExtension)];
    }
}
