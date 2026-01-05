all: target/release/net.nosial.configlib.ncc target/debug/net.nosial.configlib.ncc
target/release/net.nosial.configlib.ncc:
	ncc build --configuration release --log-level debug
target/debug/net.nosial.configlib.ncc:
	ncc build --configuration debug --log-level debug

test:
	phpunit --configuration phpunit.xml


docs:
	phpdoc --config phpdoc.dist.xml

clean:
	rm target/release/net.nosial.configlib.ncc
	rm target/debug/net.nosial.configlib.ncc
	rm target/docs
	rm target/cache

.PHONY: all install clean test docs