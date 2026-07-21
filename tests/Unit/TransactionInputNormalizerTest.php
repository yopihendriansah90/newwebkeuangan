<?php
namespace Tests\Unit;
use App\Services\TransactionInputNormalizer; use Carbon\Carbon; use PHPUnit\Framework\TestCase;
class TransactionInputNormalizerTest extends TestCase {
 public function test_normalizes_rupiah_suffixes():void { $normalizer=new TransactionInputNormalizer(); $this->assertSame(10000,$normalizer->amount('10K')); $this->assertSame(250000,$normalizer->amount('250rb')); $this->assertSame(5000000,$normalizer->amount('5jt')); $this->assertSame(10000,$normalizer->amount('Rp 10.000')); $this->assertSame(1500000,$normalizer->amount('1,5 juta')); $this->assertSame(500000,$normalizer->amount('setengah juta')); }
 public function test_normalizes_relative_and_partial_dates():void { $normalizer=new TransactionInputNormalizer(); $now=Carbon::create(2026,7,21,12); $this->assertSame('2026-07-20',$normalizer->date('kemarin',$now,7,2026)); $this->assertSame('2026-07-21',$normalizer->date('tadi',$now,7,2026)); $this->assertSame('2026-07-12',$normalizer->date('tanggal 12',$now,7,2026)); $this->assertSame('2026-06-05',$normalizer->date('tanggal 5 bulan lalu',$now,7,2026)); $this->assertSame('2026-09-12',$normalizer->date('12/09',$now,7,2026)); }
}
