<?php
use App\Services\GoalEditPolicy;use CodeIgniter\Test\CIUnitTestCase;
final class GoalEditPolicyTest extends CIUnitTestCase
{
 public function testWindowBoundariesAndClosedMonthsForEveryRole():void{$p=new GoalEditPolicy();$tz=new DateTimeZone(config('App')->appTimezone);$this->assertTrue($p->editable(2026,3,new DateTimeImmutable('2026-03-10 23:59:59',$tz)));$this->assertFalse($p->editable(2026,3,new DateTimeImmutable('2026-03-11 00:00:00',$tz)));$this->assertFalse($p->editable(2026,2,new DateTimeImmutable('2026-03-01',$tz)));$this->assertTrue($p->editable(2026,4,new DateTimeImmutable('2026-03-31',$tz)));}
}
