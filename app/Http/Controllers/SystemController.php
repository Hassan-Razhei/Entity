<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

class SystemController extends Controller
{
    /**
     * Run an Artisan command
     */
    public function runCommand(Request $request)
    {
        $request->validate([
            'command' => 'required|string',
            'args'    => 'nullable|array'
        ]);

        $command = $request->input('command');
        $args    = $request->input('args', []);

        // Whitelist allowed commands for security
        $allowedCommands = [
            'media:import-transcripts',
            'manuscript:sync',
            'manuscriptsData:sync',
            'storage:sync',
            'project:seed-realistic',
            'optimize:clear'
        ];

        if (!in_array($command, $allowedCommands)) {
            return response()->json(['message' => 'Command not allowed'], 403);
        }

        // ─── تقييد الوصول لأمر seed-realistic لمستخدمين محددين ──────────
        if ($command === 'project:seed-realistic') {
            $allowedEmails = array_filter(
                explode(',', config('app.seed_allowed_users', env('SEED_ALLOWED_USERS', '')))
            );

            $currentEmail = auth()->user()?->email;

            if (empty($allowedEmails) || !in_array($currentEmail, array_map('trim', $allowedEmails))) {
                return response()->json([
                    'message' => 'غير مصرح لك بتشغيل هذا الأمر. تواصل مع مدير النظام.'
                ], 403);
            }

            // تمرير --force تلقائياً من الويب (المستخدم وصل هنا = مُخوَّل)
            // استخدام SEED_SECRET من البيئة
            $args['--force'] = true;
            if (app()->environment('production')) {
                $args['--token'] = config('app.seed_secret', env('SEED_SECRET', ''));
            }
        }
        // ─────────────────────────────────────────────────────────────────

        try {
            $output = new BufferedOutput();
            // تمرير true لـ no-interaction كي لا يتوقف الأمر منتظراً إدخالاً
            Artisan::call($command, $args, $output);

            return response()->json([
                'status' => 'success',
                'output' => $output->fetch()
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'output'  => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * List files in a directory for selection
     */
    public function listFiles(Request $request)
    {
        // Default to user home directory if no path provided
        $currentPath = $request->input('path') ?: '/home/z';
        
        // Validation: Ensure path exists
        if (!file_exists($currentPath)) {
             return response()->json(['message' => 'Path not found', 'path' => $currentPath], 404);
        }

        // If it's a file, return its parent directory
        if (is_file($currentPath)) {
            $currentPath = dirname($currentPath);
        }

        $items = [];
        // Scan directory
        try {
            $scanned = scandir($currentPath);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Cannot read directory'], 403);
        }

        foreach ($scanned as $node) {
            if ($node === '.') continue;
            
            $fullPath = $currentPath . DIRECTORY_SEPARATOR . $node;
            // Handle '..' manually to ensure we resolve correctly
            if ($node === '..') {
                $parent = dirname($currentPath);
                $items[] = [
                    'name' => '..',
                    'path' => $parent,
                    'type' => 'folder',
                    'extension' => null
                ];
                continue;
            }

            $items[] = [
                'name' => $node,
                'path' => $fullPath,
                'type' => is_dir($fullPath) ? 'folder' : 'file',
                'extension' => is_file($fullPath) ? pathinfo($fullPath, PATHINFO_EXTENSION) : null
            ];
        }

        // Sort: Folders first (excluding '..'), then files
        usort($items, function ($a, $b) {
            if ($a['name'] === '..') return -1;
            if ($b['name'] === '..') return 1;
            
            if ($a['type'] === $b['type']) return strcasecmp($a['name'], $b['name']);
            return $a['type'] === 'folder' ? -1 : 1;
        });

        return response()->json([
            'current_path' => $currentPath,
            'parent_path' => dirname($currentPath),
            'items' => $items
        ]);
    }
}
