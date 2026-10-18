<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Files\StoreFolderRequest;
use App\Http\Resources\FileResource;
use App\Http\Resources\FolderResource;
use App\Models\File;
use App\Models\Folder;
use App\Models\Project;
use App\Services\FileService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FileService $fileService
    ) {}

    /**
     * Show file metadata.
     */
    public function show(File $file): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('view', $file)) {
            return $this->error([], 'Forbidden. You do not have permission to view this file.', 403);
        }

        $loaded = $this->fileService->getFile($file);

        return $this->success(
            new FileResource($loaded),
            'File details retrieved successfully.'
        );
    }

    /**
     * Download or stream the file.
     */
    public function download(File $file): StreamedResponse|JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('download', $file)) {
            return $this->error([], 'Forbidden. You do not have permission to download this file.', 403);
        }

        return $this->fileService->downloadFile($file);
    }

    /**
     * Delete a file.
     */
    public function destroy(File $file): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('delete', $file)) {
            return $this->error([], 'Forbidden. You do not have permission to delete this file.', 403);
        }

        $this->fileService->deleteFile($file);

        return $this->success([], 'File deleted successfully.');
    }

    /**
     * Create a folder within a project.
     */
    public function storeFolder(StoreFolderRequest $request, Project $project): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('create', Folder::class)) {
            return $this->error([], 'Forbidden. You do not have permission to create folders.', 403);
        }

        $folder = $this->fileService->createFolder($project, $user, $request->validated());

        return $this->success(
            new FolderResource($folder),
            'Folder created successfully.',
            201
        );
    }

    /**
     * Show folder details and contents.
     */
    public function showFolder(Folder $folder): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('view', $folder)) {
            return $this->error([], 'Forbidden. You do not have permission to view this folder.', 403);
        }

        $loaded = $this->fileService->getFolder($folder);

        return $this->success(
            new FolderResource($loaded),
            'Folder contents retrieved successfully.'
        );
    }

    /**
     * Delete a folder and its contents.
     */
    public function destroyFolder(Folder $folder): JsonResponse
    {
        $user = auth('api')->user();

        if (! Gate::forUser($user)->allows('delete', $folder)) {
            return $this->error([], 'Forbidden. You do not have permission to delete this folder.', 403);
        }

        $this->fileService->deleteFolder($folder);

        return $this->success([], 'Folder deleted successfully.');
    }
}
