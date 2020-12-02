<?php

namespace BrandStudio\File\Traits;

use Image;
use Storage;
use File;
use Str;

trait HasFile
{

    protected static function bootHasFile()
    {
        static::creating(function($item) {
            foreach($item->image_fields ?? [] as $field) {
                $item->{$field} = $item->processImageField($field);
            }
        });

        static::updating(function($item) {
            foreach($item->image_fields ?? [] as $field) {
                $item->{$field} = $item->processImageField($field);
            }
        });

        static::deleting(function($item) {
            foreach($item->image_fields ?? [] as $field) {
                $value = $item->{$field};
                $value = is_array($value) ? $value : [$value];
                foreach($value as $image) {
                    $item->deleteImage($image);
                }
            }
        });
    }

    protected function processImageField(string $field)
    {
        $new = $this->{$field};
        $old = $this->getOriginal($field);

        $multiple = is_array($new);

        $new = is_array($new) ? $new : [$new];
        $old = is_array($old) ? $old : [$old];

        foreach($old as $index => $image) {
            if (!in_array($image, $new)) {
                $this->deleteImage($image);
            }
        }

        foreach($new as $index => $value) {
            if (is_string($value) && Str::startsWith($value, 'data:image')) {
                $new[$index] = $this->getImageFromString($field, $value, $field);
            } else if ($value instanceof \Illuminate\Http\UploadedFile) {
                $new[$index] = $this->getImageFromUploadedFile($field, $value, $field);
            }
        }

        return $multiple ? array_filter($new) : ($new[0] ?? null);
    }

    protected function getImageFromString(string $field, string $value, $path = null)
    {
        $image = $this->base64ToImage($value)->encode('png');
        $extension = explode('/', $image->mime)[1] ?? 'png';

        $filename = $this->getFileName();

        if ($extension != 'webp' && $this->shouldSaveWebp($field)) {
            try {
                $webp = $this->base64ToImage($value)->encode('webp');
                $this->storeImage($webp, "{$filename}.webp", $path);
            } catch (\Exception $e) {
                //
            }
        }

        return $this->storeImage($image, "$filename.{$extension}", $path);
    }

    protected function getImageFromUploadedFile(string $field, $file, $path = null)
    {
        $destination_path = implode('/', array_filter([strtolower(class_basename(static::class)), $path]));

        $image_path = Storage::disk($this->getDisk())->put($destination_path, $file);

        if ($file->extension() != 'webp' && $this->shouldSaveWebp($field)) {
            try {
                $image = Image::make($file);
                $webp = $image->encode('webp');
                $filename = explode('.', basename($image_path))[0].'.webp';
                $this->storeImage($webp, $filename, $path);
            } catch(\Exception $e) {
                //
            }
        }

        return Storage::disk($this->getDisk())->url($image_path);
    }

    protected function storeImage($image, string $filename, $path = null)
    {
        $destination_path = implode('/', array_filter([strtolower(class_basename(static::class)), $path, $filename]));

        Storage::disk($this->getDisk())->put($destination_path, $image->stream());
        return Storage::disk($this->getDisk())->url($destination_path);
    }

    protected function base64ToImage(string $value)
    {
        // TODO: Handle webp
        return Image::make($value);
    }

    protected function getFileName() : string
    {
        return uniqid();
    }

    protected function deleteImage($file)
    {
        if (!$file) {
            return;
        }

        $file = str_replace(config('app.url').'/storage', '', $file);
        $webp = explode('.', $file)[0].'.webp';

        if (Storage::disk($this->getDisk())->exists($file)) {
            Storage::disk($this->getDisk())->delete($file);
        }

        if (Storage::disk($this->getDisk())->exists($webp)) {
            Storage::disk($this->getDisk())->delete($webp);
        }
    }

    public function getDisk() : string
    {
        return $this->image_disk ? $this->image_disk : config('file.default_disk', 'public');
    }

    protected function shouldSaveWebp(string $field) : bool
    {
        if (!$this->dontSaveWebp) {
            return true;
        }

        if (is_string($this->dontSaveWebp)) {
            return $this->dontSaveWebp != $field;
        }

        return !in_array($field, $this->dontSaveWebp);
    }
}
