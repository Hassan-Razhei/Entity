<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\ContentNode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Barryvdh\DomPDF\Facade\Pdf;

class BookExportController extends Controller
{
    /**
     * Export a full book in specified format.
     */
    public function exportBook(Book $book, string $format)
    {
        $children = $book->nodes()->orderBy('order')->get();
        return $this->handleExport($book->title, $children, $format);
    }

    /**
     * Export a single chapter/unit.
     */
    public function exportChild(ContentNode $child, string $format)
    {
        return $this->handleExport($child->title, collect([$child]), $format);
    }

    protected function handleExport(string $title, $items, string $format)
    {
        switch (strtolower($format)) {
            case 'markdown':
            case 'md':
                return $this->exportToMarkdown($title, $items);
            case 'pdf':
                return $this->exportToPdf($title, $items);
            case 'word':
            case 'docx':
                return $this->exportToWord($title, $items);
            default:
                return response()->json(['error' => 'Invalid format'], 400);
        }
    }

    protected function exportToMarkdown(string $title, $items)
    {
        $content = "# {$title}\n\n";
        foreach ($items as $item) {
            $content .= "## {$item->title}\n\n";
            $content .= $this->blocksToMarkdown($item->content_blocks);
            $content .= "\n\n";
        }

        return Response::make($content, 200, [
            'Content-Type' => 'text/markdown',
            'Content-Disposition' => 'attachment; filename="' . $title . '.md"',
        ]);
    }

    protected function exportToPdf(string $title, $items)
    {
        $html = view('exports.book_pdf', [
            'title' => $title,
            'items' => $items,
            'controller' => $this
        ])->render();
        $pdf = Pdf::loadHTML($html);
        return $pdf->download($title . '.pdf');
    }

    protected function exportToWord(string $title, $items)
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $section->addTitle($title, 1);

        foreach ($items as $item) {
            $section->addTitle($item->title, 2);
            foreach ($item->content_blocks as $block) {
                if ($block['type'] === 'paragraph') {
                    $text = $this->extractText($block);
                    $section->addText($text);
                }
            }
        }

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $tempFile = tempnam(sys_get_temp_dir(), 'word_');
        $objWriter->save($tempFile);

        return response()->download($tempFile, $title . '.docx')->deleteFileAfterSend(true);
    }

    protected function blocksToMarkdown(array $blocks): string
    {
        $md = "";
        foreach ($blocks as $block) {
            switch ($block['type']) {
                case 'paragraph':
                    $md .= $this->extractText($block) . "\n\n";
                    break;
                case 'heading':
                    $level = $block['attrs']['level'] ?? 1;
                    $md .= str_repeat('#', $level + 2) . " " . $this->extractText($block) . "\n\n";
                    break;
                case 'bulletList':
                    foreach ($block['content'] as $item) {
                        $md .= "* " . $this->extractText($item) . "\n";
                    }
                    $md .= "\n";
                    break;
                case 'orderedList':
                    $i = 1;
                    foreach ($block['content'] as $item) {
                        $md .= "{$i}. " . $this->extractText($item) . "\n";
                        $i++;
                    }
                    $md .= "\n";
                    break;
                case 'blockquote':
                    $md .= "> " . $this->extractText($block) . "\n\n";
                    break;
            }
        }
        return $md;
    }

    protected function extractText(array $node): string
    {
        $text = "";
        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $contentItem) {
                if ($contentItem['type'] === 'text') {
                    $itemText = $contentItem['text'];

                    // Handle marks (like footnotes)
                    if (isset($contentItem['marks']) && is_array($contentItem['marks'])) {
                        foreach ($contentItem['marks'] as $mark) {
                            if ($mark['type'] === 'scholarlyFootnote') {
                                $marker = $mark['attrs']['marker'] ?? '*';
                                $itemText .= " [^" . $marker . "]";
                            }
                        }
                    }
                    $text .= $itemText;
                } else {
                    // Recursive for lists/etc
                    $text .= $this->extractText($contentItem);
                }
            }
        }
        return $text;
    }

    public function renderNode(array $node): string
    {
        $html = "";
        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $contentItem) {
                if ($contentItem['type'] === 'text') {
                    $itemText = e($contentItem['text']);

                    if (isset($contentItem['marks']) && is_array($contentItem['marks'])) {
                        foreach ($contentItem['marks'] as $mark) {
                            if ($mark['type'] === 'scholarlyFootnote') {
                                $marker = e($mark['attrs']['marker'] ?? '*');
                                $itemText .= "<span class=\"footnote-marker\">" . $marker . "</span>";
                            }
                            if ($mark['type'] === 'bold')
                                $itemText = "<strong>$itemText</strong>";
                            if ($mark['type'] === 'italic')
                                $itemText = "<em>$itemText</em>";
                            if ($mark['type'] === 'underline')
                                $itemText = "<u>$itemText</u>";
                        }
                    }
                    $html .= $itemText;
                } else {
                    $html .= $this->renderNode($contentItem);
                }
            }
        }
        return $html;
    }
}
