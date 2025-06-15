<?php

/**
 * This example demonstrates how to use the attachment functionality in the Conversations package.
 */

use Dominservice\Conversations\Facade\Conversations;
use Dominservice\Conversations\Models\Eloquent\ConversationMessage;
use Dominservice\Conversations\Models\Eloquent\ConversationAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Example controller method to handle a message with attachments
 */
class ExampleController extends Controller
{
    /**
     * Store a message with attachments
     */
    public function storeMessageWithAttachments(Request $request, $conversationId)
    {
        // Validate the request
        $request->validate([
            'content' => 'nullable|string',
            'attachments' => 'required|array',
            'attachments.*' => 'required|file|max:10240', // 10MB max
        ]);

        // Get the attachments
        $attachments = $request->file('attachments');
        $content = $request->input('content', '');

        // Add the message with attachments
        $messageId = Conversations::addMessageWithAttachments(
            $conversationId,
            $content,
            $attachments
        );

        return response()->json([
            'message_id' => $messageId,
            'status' => 'success',
        ]);
    }

    /**
     * Get attachments for a message
     */
    public function getAttachments($conversationId, $messageId)
    {
        // Get the message with attachments
        $message = ConversationMessage::with('attachments')
            ->where('id', $messageId)
            ->where('conversation_uuid', $conversationId)
            ->first();

        if (!$message) {
            return response()->json([
                'status' => 'error',
                'message' => 'Message not found',
            ], 404);
        }

        return response()->json([
            'attachments' => $message->attachments,
        ]);
    }

    /**
     * Download an attachment
     */
    public function downloadAttachment($attachmentId)
    {
        // Get the attachment
        $attachment = ConversationAttachment::find($attachmentId);

        if (!$attachment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Attachment not found',
            ], 404);
        }

        // Check if the attachment is safe
        if (!$attachment->is_safe) {
            return response()->json([
                'status' => 'error',
                'message' => 'This file may be unsafe to download',
            ], 403);
        }

        // Get the file path
        $path = $attachment->path;
        $disk = config('conversations.attachments.disk', 'public');

        // Check if the file exists
        if (!Storage::disk($disk)->exists($path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found',
            ], 404);
        }

        // Return the file as a download
        return Storage::disk($disk)->download(
            $path,
            $attachment->original_filename,
            [
                'Content-Type' => $attachment->mime_type,
            ]
        );
    }

    /**
     * Display an image attachment
     */
    public function displayImage($attachmentId, $size = null)
    {
        // Get the attachment
        $attachment = ConversationAttachment::find($attachmentId);

        if (!$attachment || !$attachment->isImage()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Image not found',
            ], 404);
        }

        // Get the file path
        $path = $attachment->path;
        
        // If a size is specified and thumbnails are enabled, try to get the thumbnail
        if ($size && in_array($size, ['small', 'medium']) && config('conversations.attachments.image.thumbnails.enabled', true)) {
            $thumbnailPath = $attachment->getThumbnailPath($size);
            if ($thumbnailPath) {
                $path = $thumbnailPath;
            }
        }

        $disk = config('conversations.attachments.disk', 'public');

        // Check if the file exists
        if (!Storage::disk($disk)->exists($path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Image file not found',
            ], 404);
        }

        // Return the image
        $file = Storage::disk($disk)->get($path);
        $type = Storage::disk($disk)->mimeType($path);

        return response($file, 200)->header('Content-Type', $type);
    }

    /**
     * Example of handling file uploads in a form
     */
    public function showUploadForm()
    {
        return view('upload-form');
    }

    /**
     * Example of a form to upload files
     */
    public function getUploadFormHtml()
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Upload Attachments</title>
</head>
<body>
    <h1>Send a Message with Attachments</h1>
    
    <form action="/conversations/{conversationId}/messages" method="POST" enctype="multipart/form-data">
        @csrf
        
        <div>
            <label for="content">Message:</label>
            <textarea name="content" id="content" rows="4" cols="50"></textarea>
        </div>
        
        <div>
            <label for="attachments">Attachments:</label>
            <input type="file" name="attachments[]" id="attachments" multiple>
            <p>Max file size: 10MB. Allowed file types: images, documents, audio, video.</p>
        </div>
        
        <button type="submit">Send Message</button>
    </form>
    
    <script>
        // Example of client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const files = document.getElementById('attachments').files;
            const maxSize = 10 * 1024 * 1024; // 10MB
            
            for (let i = 0; i < files.length; i++) {
                if (files[i].size > maxSize) {
                    alert('File ' + files[i].name + ' is too large. Maximum size is 10MB.');
                    e.preventDefault();
                    return;
                }
            }
        });
    </script>
</body>
</html>
HTML;
    }
}