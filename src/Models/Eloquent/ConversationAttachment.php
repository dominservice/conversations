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

namespace Dominservice\Conversations\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Class ConversationAttachment
 * @package Dominservice\Conversations\Models\Eloquent
 */
class ConversationAttachment extends Model
{
    public const TYPE_FILE = 'file';
    public const TYPE_IMAGE = 'image';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_AUDIO = 'audio';
    public const TYPE_VIDEO = 'video';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'message_id',
        'filename',
        'original_filename',
        'mime_type',
        'extension',
        'type',
        'size',
        'path',
        'metadata',
        'is_optimized',
        'is_scanned',
        'is_safe',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
        'is_optimized' => 'boolean',
        'is_scanned' => 'boolean',
        'is_safe' => 'boolean',
        'size' => 'integer',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('conversations.tables.conversation_attachments');
    }

    /**
     * Get the message that owns the attachment.
     */
    public function message()
    {
        return $this->belongsTo(ConversationMessage::class, 'message_id');
    }

    /**
     * Get the full URL to the attachment.
     *
     * @return string
     */
    public function getUrlAttribute()
    {
        return Storage::disk(config('conversations.attachments.disk'))->url($this->path);
    }

    /**
     * Get the full path to the attachment.
     *
     * @return string
     */
    public function getFullPathAttribute()
    {
        return Storage::disk(config('conversations.attachments.disk'))->path($this->path);
    }

    /**
     * Get the thumbnail URL for image attachments.
     *
     * @param string $size small|medium
     * @return string|null
     */
    public function getThumbnailUrl($size = 'small')
    {
        if ($this->type !== self::TYPE_IMAGE || !config('conversations.attachments.image.thumbnails.enabled')) {
            return null;
        }

        $thumbnailPath = $this->getThumbnailPath($size);
        if (!$thumbnailPath) {
            return null;
        }

        return Storage::disk(config('conversations.attachments.disk'))->url($thumbnailPath);
    }

    /**
     * Get the thumbnail path for image attachments.
     *
     * @param string $size small|medium
     * @return string|null
     */
    public function getThumbnailPath($size = 'small')
    {
        if ($this->type !== self::TYPE_IMAGE || !config('conversations.attachments.image.thumbnails.enabled')) {
            return null;
        }

        $pathInfo = pathinfo($this->path);
        $thumbnailPath = $pathInfo['dirname'] . '/' . 
            $pathInfo['filename'] . '_' . $size . '.' . $pathInfo['extension'];

        if (!Storage::disk(config('conversations.attachments.disk'))->exists($thumbnailPath)) {
            return null;
        }

        return $thumbnailPath;
    }

    /**
     * Check if the attachment is an image.
     *
     * @return bool
     */
    public function isImage()
    {
        return $this->type === self::TYPE_IMAGE;
    }

    /**
     * Check if the attachment is a document.
     *
     * @return bool
     */
    public function isDocument()
    {
        return $this->type === self::TYPE_DOCUMENT;
    }

    /**
     * Check if the attachment is an audio file.
     *
     * @return bool
     */
    public function isAudio()
    {
        return $this->type === self::TYPE_AUDIO;
    }

    /**
     * Check if the attachment is a video file.
     *
     * @return bool
     */
    public function isVideo()
    {
        return $this->type === self::TYPE_VIDEO;
    }

    /**
     * Check if the attachment requires a warning.
     *
     * @return bool
     */
    public function requiresWarning()
    {
        if (!$this->is_safe) {
            return true;
        }

        $warningTypes = config('conversations.attachments.security.warning_types', []);
        return in_array(strtolower($this->extension), $warningTypes);
    }

    /**
     * Get the human-readable file size.
     *
     * @return string
     */
    public function getHumanSizeAttribute()
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Delete the attachment file from storage when the model is deleted.
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($attachment) {
            // Delete the main file
            Storage::disk(config('conversations.attachments.disk'))->delete($attachment->path);

            // Delete thumbnails if they exist
            if ($attachment->type === self::TYPE_IMAGE && 
                config('conversations.attachments.image.thumbnails.enabled')) {
                foreach (['small', 'medium'] as $size) {
                    $thumbnailPath = $attachment->getThumbnailPath($size);
                    if ($thumbnailPath) {
                        Storage::disk(config('conversations.attachments.disk'))->delete($thumbnailPath);
                    }
                }
            }
        });
    }
}
