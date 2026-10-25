<?php

namespace FreshCart\Operations\Services;

use FreshCart\Operations\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photos, proof-of-delivery and disposal evidence. Files are kept on the private
 * `local` disk and only served through authorized controller actions.
 */
class AttachmentService
{
    /**
     * Validation rules for uploads (images and PDFs, 5 MB).
     */
    const RULES = 'file|mimes:jpg,jpeg,png,webp,pdf|max:5120';

    const DISK = 'local';

    public function store(Model $owner, UploadedFile $file, string $collection): Attachment
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());

        $path = $file->storeAs(
            'operations/'.$collection.'/'.now()->format('Y/m'),
            Str::uuid().'.'.$extension,
            self::DISK,
        );

        return Attachment::create([
            'attachable_type' => $owner->getMorphClass(),
            'attachable_id' => $owner->getKey(),
            'collection' => $collection,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'admin_id' => auth()->guard('admin')->id(),
        ]);
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk(self::DISK)->exists($attachment->path), 404);

        return Storage::disk(self::DISK)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
