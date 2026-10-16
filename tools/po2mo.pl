use strict;
use warnings;

# PO -> MO converter equivalent to languages/generate-mo.php
# Usage: perl po2mo.pl <file.po> <file.mo>

my ($po_file, $mo_file) = @ARGV;
die "usage: perl po2mo.pl file.po file.mo\n" unless $po_file && $mo_file;

open(my $in, '<:raw', $po_file) or die "open $po_file: $!";
local $/;
my $po = <$in>;
close $in;
$po =~ s/\r\n/\n/g;

my %entries;
my @order;
my ($mid, $mstr, $which) = ('', '', '');

sub flush {
    if ($mid ne '' || $mstr ne '') {
        my $k = $mid;
        my $v = $mstr;
        $k =~ s/\\(["\\])/$1/g;
        $v =~ s/\\(["\\])/$1/g;
        if ($k ne '') {
            push @order, $k unless exists $entries{$k};
            $entries{$k} = $v;
        }
    }
    ($mid, $mstr, $which) = ('', '', '');
}

for my $line (split /\n/, $po) {
    $line =~ s/^\s+//;
    $line =~ s/\s+$//;
    if ($line eq '' || substr($line, 0, 1) eq '#') { flush(); next; }
    if ($line =~ /^msgid "(.*)"$/)        { flush(); $mid = $1; $which = 'id'; }
    elsif ($line =~ /^msgstr "(.*)"$/)    { $mstr = $1; $which = 'str'; }
    elsif ($line =~ /^"(.*)"$/) {
        if    ($which eq 'id')  { $mid  .= $1; }
        elsif ($which eq 'str') { $mstr .= $1; }
    }
}
flush();
delete $entries{''};

my $count = scalar keys %entries;
my $mo = pack('L', 0x950412de) . pack('L', 0) . pack('L', $count)
    . pack('L', 28) . pack('L', 28 + $count * 8) . pack('L', 0) . pack('L', 0);

my @keys = @order;
my ($ot, $tt, $os, $ts) = ('', '', '', '');
my $offset = 28 + $count * 16;
for my $k (@keys) {
    $ot .= pack('L', length($k)) . pack('L', $offset);
    $os .= $k . "\0";
    $offset += length($k) + 1;
}
for my $k (@keys) {
    my $v = $entries{$k};
    $tt .= pack('L', length($v)) . pack('L', $offset);
    $ts .= $v . "\0";
    $offset += length($v) + 1;
}
$mo .= $ot . $tt . $os . $ts;

open(my $out, '>:raw', $mo_file) or die "open $mo_file: $!";
print $out $mo;
close $out;
print "Generated $mo_file ($count entries)\n";
