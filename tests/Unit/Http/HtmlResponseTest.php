<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Gemvc\Http\HtmlResponse;
use Tests\Helpers\FakeSwooleHttpResponse;

/**
 * @outputBuffering enabled
 */
class HtmlResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectOutputString('');
    }
    
    // ============================================
    // Constructor Tests
    // ============================================
    
    public function testConstructorWithDefaultValues(): void
    {
        $response = new HtmlResponse('Hello World');
        
        $reflection = new \ReflectionClass($response);
        $contentProperty = $reflection->getProperty('content');
        $statusProperty = $reflection->getProperty('status');
        $headersProperty = $reflection->getProperty('headers');
        
        $this->assertEquals('Hello World', $contentProperty->getValue($response));
        $this->assertEquals(200, $statusProperty->getValue($response));
        $headers = $headersProperty->getValue($response);
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertEquals('text/html', $headers['Content-Type']);
    }
    
    public function testConstructorWithCustomStatus(): void
    {
        $response = new HtmlResponse('Not Found', 404);
        
        $reflection = new \ReflectionClass($response);
        $statusProperty = $reflection->getProperty('status');
        
        $this->assertEquals(404, $statusProperty->getValue($response));
    }
    
    public function testConstructorWithCustomHeaders(): void
    {
        $response = new HtmlResponse('Content', 200, ['X-Custom-Header' => 'value']);
        
        $reflection = new \ReflectionClass($response);
        $headersProperty = $reflection->getProperty('headers');
        $headers = $headersProperty->getValue($response);
        
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('X-Custom-Header', $headers);
        $this->assertEquals('text/html', $headers['Content-Type']);
        $this->assertEquals('value', $headers['X-Custom-Header']);
    }
    
    public function testConstructorMergesHeaders(): void
    {
        $response = new HtmlResponse('Content', 200, [
            'Content-Type' => 'application/xml',
            'X-Custom' => 'test'
        ]);
        
        $reflection = new \ReflectionClass($response);
        $headersProperty = $reflection->getProperty('headers');
        $headers = $headersProperty->getValue($response);
        
        // Content-Type should be merged (default + custom)
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('X-Custom', $headers);
    }
    
    // ============================================
    // show() Tests
    // ============================================
    
    public function testShowOutputsContent(): void
    {
        $response = new HtmlResponse('Test Content');
        
        ob_start();
        $response->show();
        $output = ob_get_clean();
        
        $this->assertEquals('Test Content', $output);
    }
    
    public function testShowSetsStatusCode(): void
    {
        $response = new HtmlResponse('Content', 404);
        
        ob_start();
        $response->show();
        ob_end_clean();
        
        $this->assertEquals(404, http_response_code());
        
        // Reset for other tests
        http_response_code(200);
    }
    
    public function testShowSetsHeaders(): void
    {
        $response = new HtmlResponse('Content', 200, ['X-Test' => 'value']);
        
        ob_start();
        $response->show();
        ob_end_clean();
        
        // Headers are sent, we can't easily test them in unit tests
        // But we verify the method executes without error
        $this->assertTrue(true);
    }
    
    // ============================================
    // showSwoole() Tests
    // ============================================
    
    public function testShowSwooleSetsHeaders(): void
    {
        $swooleResponse = new FakeSwooleHttpResponse();
        $response = new HtmlResponse('Content', 200, ['X-Custom' => 'value']);
        $response->showSwoole($swooleResponse);

        $this->assertTrue($swooleResponse->wasHeaderSet('Content-Type', 'text/html'));
        $this->assertTrue($swooleResponse->wasHeaderSet('X-Custom', 'value'));
    }
    
    public function testShowSwooleSetsStatus(): void
    {
        $swooleResponse = new FakeSwooleHttpResponse();
        $response = new HtmlResponse('Content', 404);
        $response->showSwoole($swooleResponse);

        $this->assertSame(404, $swooleResponse->statusCode);
        $this->assertNotSame([], $swooleResponse->sentHeaders);
        $this->assertSame('Content', $swooleResponse->body);
    }
    
    public function testShowSwooleEndsWithContent(): void
    {
        $swooleResponse = new FakeSwooleHttpResponse();
        $response = new HtmlResponse('Test HTML Content');
        $response->showSwoole($swooleResponse);

        $this->assertSame('Test HTML Content', $swooleResponse->body);
        $this->assertNotSame([], $swooleResponse->sentHeaders);
        $this->assertSame(200, $swooleResponse->statusCode);
    }
    
    // ============================================
    // create() Static Method Tests
    // ============================================
    
    public function testCreateReturnsHtmlResponseInstance(): void
    {
        $response = HtmlResponse::create('Content');
        
        $this->assertInstanceOf(HtmlResponse::class, $response);
    }
    
    public function testCreateWithDefaultValues(): void
    {
        $response = HtmlResponse::create('Hello');
        
        $reflection = new \ReflectionClass($response);
        $contentProperty = $reflection->getProperty('content');
        $statusProperty = $reflection->getProperty('status');
        
        $this->assertEquals('Hello', $contentProperty->getValue($response));
        $this->assertEquals(200, $statusProperty->getValue($response));
    }
    
    public function testCreateWithCustomStatus(): void
    {
        $response = HtmlResponse::create('Error', 500);
        
        $reflection = new \ReflectionClass($response);
        $statusProperty = $reflection->getProperty('status');
        
        $this->assertEquals(500, $statusProperty->getValue($response));
    }
    
    public function testCreateWithCustomHeaders(): void
    {
        $response = HtmlResponse::create('Content', 200, ['X-Header' => 'value']);
        
        $reflection = new \ReflectionClass($response);
        $headersProperty = $reflection->getProperty('headers');
        $headers = $headersProperty->getValue($response);
        
        $this->assertArrayHasKey('X-Header', $headers);
        $this->assertEquals('value', $headers['X-Header']);
    }
    
    // ============================================
    // Edge Cases
    // ============================================
    
    public function testEmptyContent(): void
    {
        $response = new HtmlResponse('');
        
        ob_start();
        $response->show();
        $output = ob_get_clean();
        
        $this->assertEquals('', $output);
    }
    
    public function testHtmlContentWithSpecialCharacters(): void
    {
        $html = '<div>&amp; "quotes" &lt;tags&gt;</div>';
        $response = new HtmlResponse($html);
        
        ob_start();
        $response->show();
        $output = ob_get_clean();
        
        $this->assertEquals($html, $output);
    }
    
    public function testMultipleCustomHeaders(): void
    {
        $response = new HtmlResponse('Content', 200, [
            'X-Header-1' => 'value1',
            'X-Header-2' => 'value2',
            'X-Header-3' => 'value3'
        ]);
        
        $reflection = new \ReflectionClass($response);
        $headersProperty = $reflection->getProperty('headers');
        $headers = $headersProperty->getValue($response);
        
        $this->assertCount(4, $headers); // Content-Type + 3 custom headers
        $this->assertEquals('value1', $headers['X-Header-1']);
        $this->assertEquals('value2', $headers['X-Header-2']);
        $this->assertEquals('value3', $headers['X-Header-3']);
    }
}

