<?php

namespace App\Controller;

use League\Flysystem\Filesystem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(Filesystem $defaultAdapter, UrlGeneratorInterface $urlGenerator): Response
    {
        $files = $defaultAdapter->listContents('/', Filesystem::LIST_DEEP);

        $realFiles = [];
        
        foreach ($files as $key => $file) {
            if ($file['type'] === 'file' && !str_starts_with($file['path'], '.')) {
                $realFiles[] = [
                    'path' => $file['path'],
                    'url' => $this->generateUrl('app_file_proxy', ['filename' => $file['path']], UrlGeneratorInterface::ABSOLUTE_URL),
                ];
            }
        }


        return $this->render('dashboard/index.html.twig', [
            'files' => $realFiles,
        ]);
    }

    #[Route('/file-proxy', name: 'app_file_proxy')]
    public function fileProxy(Filesystem $defaultAdapter, #[MapQueryParameter('filename')]string $filename)
    {
        $mimetype = $defaultAdapter->mimeType($filename);
        if ($mimetype === '') {
            $mimetype = 'application/octet-stream';
        }

        $response = new StreamedResponse(static function () use ($filename, $defaultAdapter): void {
            $outputStream = fopen('php://output', 'w');
            $fileStream = $defaultAdapter->readStream($filename);
            stream_copy_to_stream($fileStream, $outputStream);
        });

        $response->headers->set('Content-Type', $mimetype);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            basename($filename)
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;

    }
}
