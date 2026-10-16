#!/usr/bin/perl
# Crude brace/paren/bracket balance check for PHP files (php -l substitute).
# Strips comments and quoted strings first. Usage: perl balance-check.pl <file>...
use strict;
use warnings;

my %pairs = ('{' => '}', '(' => ')', '[' => ']');
my %closers = map { $_ => 1 } values %pairs;
my $fail = 0;

for my $file (@ARGV) {
    open my $fh, '<', $file or do { print "$file: cannot open\n"; $fail = 1; next };
    local $/;
    my $c = <$fh>;
    close $fh;

    $c =~ s{/\*.*?\*/}{}gs;          # block comments
    $c =~ s{//[^\n]*}{}g;            # line comments
    $c =~ s{\#[^\n]*}{}g;            # hash comments
    $c =~ s{'(?:\\.|[^'\\])*'}{''}gs;  # single-quoted
    $c =~ s{"(?:\\.|[^"\\])*"}{""}gs;  # double-quoted

    my @stack;
    my $line = 1;
    my $bad = '';
    for my $ch (split //, $c) {
        $line++ if $ch eq "\n";
        if ($pairs{$ch}) {
            push @stack, [$ch, $line];
        } elsif ($closers{$ch}) {
            my $top = pop @stack;
            if (!$top || $pairs{$top->[0]} ne $ch) {
                $bad = "mismatched '$ch' at line $line";
                last;
            }
        }
    }

    if ($bad) {
        print "$file: FAIL ($bad)\n"; $fail = 1;
    } elsif (@stack) {
        printf "%s: FAIL (%d unclosed, first '%s' at line %d)\n",
            $file, scalar(@stack), $stack[0][0], $stack[0][1];
        $fail = 1;
    } else {
        print "$file: BALANCED\n";
    }
}
exit $fail;
