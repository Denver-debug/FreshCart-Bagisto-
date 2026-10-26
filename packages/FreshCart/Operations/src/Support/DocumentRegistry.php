<?php

namespace FreshCart\Operations\Support;

use FreshCart\Operations\Models\Adjustment;
use FreshCart\Operations\Models\PurchaseRequest;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Maps approvable document types to their ACL key, threshold setting and view route.
 */
class DocumentRegistry
{
    const DOCUMENTS = [
        'purchase_requests' => [
            'model' => PurchaseRequest::class,
            'route' => 'admin.operations.purchase_requests.view',
            'threshold' => 'purchase_requests',
        ],
        'adjustments' => [
            'model' => Adjustment::class,
            'route' => 'admin.operations.adjustments.view',
            'threshold' => 'adjustments',
        ],
    ];

    public function get(string $key): array
    {
        if (! isset(self::DOCUMENTS[$key])) {
            throw new InvalidArgumentException("Unknown approvable document [$key].");
        }

        return self::DOCUMENTS[$key] + ['key' => $key];
    }

    public function keyFor(Model $document): string
    {
        foreach (self::DOCUMENTS as $key => $definition) {
            if ($document instanceof $definition['model']) {
                return $key;
            }
        }

        throw new InvalidArgumentException('Document type is not approvable: '.get_class($document));
    }

    public function approvePermission(string $key): string
    {
        return 'operations.'.$key.'.approve';
    }

    public function url(Model $document): string
    {
        return route($this->get($this->keyFor($document))['route'], $document->getKey());
    }

    public function label(Model $document): string
    {
        return trans('operations::app.documents.'.$this->keyFor($document)).' '.$document->reference;
    }
}
