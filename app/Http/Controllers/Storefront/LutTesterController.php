<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\LutTestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLutTestUploadRequest;
use App\Jobs\ProcessLutTestUpload;
use App\Models\LutTestUpload;
use App\Models\Product;
use App\Services\LutTester\DeleteLutTestUpload;
use App\Services\LutTester\LutTestPresenter;
use App\Services\LutTester\ProductLutTestEligibility;
use App\Services\LutTester\ResolveProductPreviewLut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class LutTesterController extends Controller
{
    public function create(
        string $slug,
        ProductLutTestEligibility $eligibility,
        LutTestPresenter $presenter,
    ): Response {
        $product = $this->publishedProduct($slug);

        abort_unless($eligibility->canTest($product), 404);

        return Inertia::render('Shop/Try', [
            'product' => $presenter->product($product),
            'test' => null,
        ]);
    }

    public function store(
        string $slug,
        StoreLutTestUploadRequest $request,
        ProductLutTestEligibility $eligibility,
        ResolveProductPreviewLut $resolver,
    ): RedirectResponse {
        $product = $this->publishedProduct($slug);

        abort_unless($eligibility->canTest($product), 404);

        try {
            $lut = $resolver->resolve($product);
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'photo' => 'This LUT is currently unavailable for online testing.',
            ]);
        }

        $file = $request->file('photo');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'photo' => 'Please choose a photo to upload.',
            ]);
        }

        $dimensions = getimagesize($file->getRealPath() ?: '');

        if ($dimensions === false) {
            throw ValidationException::withMessages([
                'photo' => 'The photo could not be inspected.',
            ]);
        }

        $diskName = (string) config('lut-tester.disk', 'private');
        $id = (string) Str::ulid();
        $directory = trim((string) config('lut-tester.prefix', 'lut-tests'), '/')
            .'/'.$request->user()->id.'/'.$id;
        $rawPath = $this->storeRawUpload($file, $diskName, $directory);

        try {
            $upload = DB::transaction(function () use ($request, $product, $lut, $file, $dimensions, $diskName, $rawPath, $id): LutTestUpload {
                $upload = LutTestUpload::query()->create([
                    'id' => $id,
                    'user_id' => $request->user()->id,
                    'product_id' => $product->id,
                    'product_version_id' => $lut->version->id,
                    'product_file_id' => $lut->file->id,
                    'status' => LutTestStatus::Queued,
                    'disk' => $diskName,
                    'raw_path' => $rawPath,
                    'original_name' => $this->safeOriginalName($file->getClientOriginalName()),
                    'original_mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'original_size_bytes' => $file->getSize() ?: 0,
                    'original_width' => (int) $dimensions[0],
                    'original_height' => (int) $dimensions[1],
                    'expires_at' => now()->addMinutes((int) config('lut-tester.expires_minutes', 60)),
                ]);

                ProcessLutTestUpload::dispatch($upload)->afterCommit();

                return $upload;
            });
        } catch (\Throwable $exception) {
            Storage::disk($diskName)->delete($rawPath);

            throw $exception;
        }

        return redirect()->route('shop.tester.show', [
            'slug' => $product->slug,
            'lutTestUpload' => $upload->id,
        ]);
    }

    public function show(string $slug, LutTestUpload $lutTestUpload, LutTestPresenter $presenter): Response
    {
        $product = $this->publishedProduct($slug);

        abort_unless($lutTestUpload->product_id === $product->id, 404);
        Gate::authorize('view', $lutTestUpload);
        $lutTestUpload->setRelation('product', $product);

        return Inertia::render('Shop/Try', [
            'product' => $presenter->product($product),
            'test' => $presenter->test($lutTestUpload),
        ]);
    }

    public function destroy(string $slug, LutTestUpload $lutTestUpload, DeleteLutTestUpload $delete): RedirectResponse
    {
        $product = $this->publishedProduct($slug);

        abort_unless($lutTestUpload->product_id === $product->id, 404);
        Gate::authorize('delete', $lutTestUpload);

        $delete->delete($lutTestUpload);

        return redirect()->route('shop.tester.create', $product->slug);
    }

    private function publishedProduct(string $slug): Product
    {
        return Product::query()
            ->select([
                'id',
                'type',
                'status',
                'name',
                'slug',
                'short_description',
                'price_cents',
                'currency',
                'is_featured',
                'is_testable',
                'published_at',
            ])
            ->published()
            ->with([
                'coverMedia:id,product_id,kind,path,alt_text,width,height,sort_order',
                'currentVersion:id,product_id,version,status,is_current,released_at',
                'currentVersion.files:id,product_version_id,kind,disk,path',
            ])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function storeRawUpload(UploadedFile $file, string $diskName, string $directory): string
    {
        $extension = match ($file->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $path = $file->storeAs($directory.'/raw', Str::random(40).'.'.$extension, [
            'disk' => $diskName,
        ]);

        if ($path === false) {
            throw new RuntimeException('Unable to store the uploaded photo.');
        }

        return $path;
    }

    private function safeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));

        return $name !== '' ? $name : 'photo';
    }
}
