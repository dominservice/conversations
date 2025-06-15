<?php

/**
 * Conversations
 *
 * This package will allow you to add a full user messaging system
 * into your Laravel application.
 *
 * @package   Dominservice\Conversations
 * @author    DSO-IT Mateusz Domin <biuro@dso.biz.pl>
 * @copyright (c) 2021 DSO-IT Mateusz Domin
 * @license   MIT
 * @version   3.0.0
 */

namespace Dominservice\Conversations\Services;

use Dominservice\Conversations\Models\Eloquent\ConversationAttachment;
use Dominservice\Conversations\Models\Eloquent\ConversationMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class AttachmentService
{
    /**
     * Process and store an uploaded file.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $messageId
     * @return \Dominservice\Conversations\Models\Eloquent\ConversationAttachment|null
     */
    public function storeAttachment(UploadedFile $file, $messageId)
    {
        // Execute before_validate hook
        $hookResult = app('Dominservice\Conversations\Hooks\HookManager')->execute('before_validate', [
            'file' => $file,
            'message_id' => $messageId,
        ]);

        // If a hook returns false, abort the operation
        if ($hookResult === false) {
            return null;
        }

        // Validate the file
        if (!$this->validateFile($file)) {
            return null;
        }

        // Get file information
        $originalFilename = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        $size = $file->getSize();
        $type = $this->getFileType($extension, $mimeType);

        // Generate a unique filename
        $filename = Str::uuid() . '.' . $extension;
        $path = config('conversations.attachments.path') . '/' . date('Y/m/d') . '/' . $filename;

        // Execute before_store hook
        $hookResult = app('Dominservice\Conversations\Hooks\HookManager')->execute('before_store', [
            'file' => $file,
            'message_id' => $messageId,
            'path' => $path,
            'type' => $type,
        ]);

        // If a hook returns false, abort the operation
        if ($hookResult === false) {
            return null;
        }

        // Prepare metadata
        $metadata = [];
        $isOptimized = false;

        // Handle image optimization if enabled and file is an image
        if ($type === ConversationAttachment::TYPE_IMAGE && config('conversations.attachments.image.optimize', true)) {
            $result = $this->processImage($file, $path, $extension);
            if ($result) {
                $path = $result['path'];
                $metadata = $result['metadata'];
                $isOptimized = $result['is_optimized'];
                $size = $result['size'];
            }
        } else {
            // Store the file
            Storage::disk(config('conversations.attachments.disk'))->putFileAs(
                dirname($path),
                $file,
                basename($path)
            );
        }

        // Scan for viruses if enabled
        $isScanned = false;
        $isSafe = true;

        if (config('conversations.attachments.security.virus_scan.enabled', false)) {
            $scanResult = $this->scanFile($path);
            $isScanned = true;
            $isSafe = $scanResult;
        }

        // Create the attachment record
        $attachment = new ConversationAttachment([
            'message_id' => $messageId,
            'filename' => $filename,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'type' => $type,
            'size' => $size,
            'path' => $path,
            'metadata' => $metadata,
            'is_optimized' => $isOptimized,
            'is_scanned' => $isScanned,
            'is_safe' => $isSafe,
        ]);

        $attachment->save();

        // Execute after_store hook
        app('Dominservice\Conversations\Hooks\HookManager')->execute('after_store', [
            'attachment' => $attachment,
            'message_id' => $messageId,
        ]);

        return $attachment;
    }

    /**
     * Validate the uploaded file.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return bool
     */
    protected function validateFile(UploadedFile $file)
    {
        // Check if the file is valid
        if (!$file->isValid()) {
            return false;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        $size = $file->getSize();
        $type = $this->getFileType($extension, $mimeType);

        // Check if the extension is blocked
        $blockedExtensions = config('conversations.attachments.blocked_extensions', []);
        if (in_array($extension, $blockedExtensions)) {
            return false;
        }

        // Check if the extension is allowed for the file type
        $allowedExtensions = config('conversations.attachments.allowed_extensions.' . $type, []);
        if (!empty($allowedExtensions) && !in_array($extension, $allowedExtensions)) {
            return false;
        }

        // Check file size
        $maxSize = config('conversations.attachments.max_size.' . $type, 
                   config('conversations.attachments.max_size.default', 10240)) * 1024; // Convert KB to bytes
        if ($size > $maxSize) {
            return false;
        }

        return true;
    }

    /**
     * Determine the file type based on extension and MIME type.
     *
     * @param string $extension
     * @param string $mimeType
     * @return string
     */
    protected function getFileType($extension, $mimeType)
    {
        // Check if it's an image
        $imageExtensions = config('conversations.attachments.allowed_extensions.image', []);
        if (in_array($extension, $imageExtensions) || strpos($mimeType, 'image/') === 0) {
            return ConversationAttachment::TYPE_IMAGE;
        }

        // Check if it's a document
        $documentExtensions = config('conversations.attachments.allowed_extensions.document', []);
        if (in_array($extension, $documentExtensions) || 
            strpos($mimeType, 'application/pdf') === 0 ||
            strpos($mimeType, 'application/msword') === 0 ||
            strpos($mimeType, 'application/vnd.openxmlformats-officedocument') === 0 ||
            strpos($mimeType, 'text/') === 0) {
            return ConversationAttachment::TYPE_DOCUMENT;
        }

        // Check if it's an audio file
        $audioExtensions = config('conversations.attachments.allowed_extensions.audio', []);
        if (in_array($extension, $audioExtensions) || strpos($mimeType, 'audio/') === 0) {
            return ConversationAttachment::TYPE_AUDIO;
        }

        // Check if it's a video file
        $videoExtensions = config('conversations.attachments.allowed_extensions.video', []);
        if (in_array($extension, $videoExtensions) || strpos($mimeType, 'video/') === 0) {
            return ConversationAttachment::TYPE_VIDEO;
        }

        // Default to generic file
        return ConversationAttachment::TYPE_FILE;
    }

    /**
     * Process and optimize an image.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $path
     * @param string $extension
     * @return array|null
     */
    protected function processImage(UploadedFile $file, $path, $extension)
    {
        try {
            // Load the image
            $image = Image::read($file);
            $originalWidth = $image->width();
            $originalHeight = $image->height();

            // Resize if needed
            $maxDimensions = config('conversations.attachments.image.max_dimensions', [1920, 1080]);
            if ($originalWidth > $maxDimensions[0] || $originalHeight > $maxDimensions[1]) {
                $image = $image->resize(
                    $maxDimensions[0], 
                    $maxDimensions[1], 
                    function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    }
                );
            }

            // Convert format if needed
            $convertTo = config('conversations.attachments.image.convert_to');
            if ($convertTo && $extension !== $convertTo) {
                $extension = $convertTo;
                $path = substr($path, 0, strrpos($path, '.')) . '.' . $convertTo;
            }

            // Set quality
            $quality = config('conversations.attachments.image.quality', 85);

            // Save the image
            $disk = Storage::disk(config('conversations.attachments.disk'));
            $disk->makeDirectory(dirname($path));

            // Create encoder based on extension
            $encoder = match ($extension) {
                'jpg', 'jpeg' => $image->encodeByExtension($extension, $quality),
                'png' => $image->encodeByExtension($extension),
                'webp' => $image->encodeByExtension($extension, $quality),
                'gif' => $image->encodeByExtension($extension),
                default => $image->encodeByExtension($extension, $quality),
            };

            // Save the encoded image
            $disk->put($path, $encoder->toString());

            // Generate thumbnails if enabled
            if (config('conversations.attachments.image.thumbnails.enabled', true)) {
                $this->generateThumbnails($image, $path, $extension, $quality);
            }

            // Get the new file size
            $size = $disk->size($path);

            // Prepare metadata
            $metadata = [
                'width' => $image->width(),
                'height' => $image->height(),
                'original_width' => $originalWidth,
                'original_height' => $originalHeight,
            ];

            return [
                'path' => $path,
                'metadata' => $metadata,
                'is_optimized' => true,
                'size' => $size,
            ];
        } catch (\Exception $e) {
            // If image processing fails, store the original file
            Storage::disk(config('conversations.attachments.disk'))->putFileAs(
                dirname($path),
                $file,
                basename($path)
            );

            return null;
        }
    }

    /**
     * Generate thumbnails for an image.
     *
     * @param \Intervention\Image\Interfaces\ImageInterface $image
     * @param string $path
     * @param string $extension
     * @param int $quality
     * @return void
     */
    protected function generateThumbnails($image, $path, $extension, $quality)
    {
        $thumbnailSizes = config('conversations.attachments.image.thumbnails.dimensions', []);
        $disk = Storage::disk(config('conversations.attachments.disk'));

        foreach ($thumbnailSizes as $name => $dimensions) {
            $thumbnailPath = substr($path, 0, strrpos($path, '.')) . '_' . $name . '.' . $extension;

            // Create a resized version of the image for the thumbnail
            $thumbnail = $image->resize(
                $dimensions[0], 
                $dimensions[1], 
                function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                }
            );

            // Create encoder based on extension
            $encoder = match ($extension) {
                'jpg', 'jpeg' => $thumbnail->encodeByExtension($extension, $quality),
                'png' => $thumbnail->encodeByExtension($extension),
                'webp' => $thumbnail->encodeByExtension($extension, $quality),
                'gif' => $thumbnail->encodeByExtension($extension),
                default => $thumbnail->encodeByExtension($extension, $quality),
            };

            // Save the encoded thumbnail
            $disk->put($thumbnailPath, $encoder->toString());
        }
    }

    /**
     * Scan a file for viruses.
     *
     * @param string $path
     * @return bool
     */
    protected function scanFile($path)
    {
        $driver = config('conversations.attachments.security.virus_scan.driver', 'clamav');

        if ($driver === 'clamav' && extension_loaded('clamav')) {
            return $this->scanWithClamAV($path);
        } elseif ($driver === 'external') {
            return $this->scanWithExternalService($path);
        }

        // Default to safe if scanning is not available
        return true;
    }

    /**
     * Scan a file with ClamAV.
     *
     * @param string $path
     * @return bool
     */
    protected function scanWithClamAV($path)
    {
        try {
            $socket = config('conversations.attachments.security.virus_scan.clamav.socket');
            $fullPath = Storage::disk(config('conversations.attachments.disk'))->path($path);

            if (function_exists('clamav_scan_file')) {
                $result = clamav_scan_file($fullPath);
                // CL_CLEAN is typically 0 in ClamAV
                return $result === 0;
            }

            return true;
        } catch (\Exception $e) {
            return true; // Default to safe if scanning fails
        }
    }

    /**
     * Scan a file with an external service.
     *
     * @param string $path
     * @return bool
     */
    protected function scanWithExternalService($path)
    {
        try {
            $apiUrl = config('conversations.attachments.security.virus_scan.external.api_url');
            $apiKey = config('conversations.attachments.security.virus_scan.external.api_key');

            if (empty($apiUrl) || empty($apiKey)) {
                return true;
            }

            $fullPath = Storage::disk(config('conversations.attachments.disk'))->path($path);

            // Implementation would depend on the external service API
            // This is a placeholder for custom implementation

            return true;
        } catch (\Exception $e) {
            return true; // Default to safe if scanning fails
        }
    }
}
