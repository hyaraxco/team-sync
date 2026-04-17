<?php

namespace App\Interfaces;

interface ProjectTaskRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?int $projectId,
        ?int $limit,
        bool $execute
    );

    public function getAllPaginated(
        ?string $search,
        ?int $projectId,
        int $rowPerPage
    );

    public function getById(
        string $id
    );

    public function create(
        array $data
    );

    public function update(
        string $id,
        array $data
    );

    public function delete(
        string $id
    );

    public function getByProjectId(
        int $projectId
    );

    public function getComments(
        string $taskId
    );

    public function createComment(
        string $taskId,
        array $data
    );

    public function updateComment(
        string $taskId,
        string $commentId,
        array $data
    );

    public function deleteComment(
        string $taskId,
        string $commentId
    );

    public function getAttachments(
        string $taskId
    );

    public function getStatusLogs(
        string $taskId
    );

    public function createAttachment(
        string $taskId,
        array $data
    );

    public function deleteAttachment(
        string $taskId,
        string $attachmentId
    );
}
