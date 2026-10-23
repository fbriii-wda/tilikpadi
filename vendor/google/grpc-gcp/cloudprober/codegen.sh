#!/usr/bin/env bash
cd "$(dirname "$0")"

rm -rf google

for p in $(find ../third_party/googleapis/google -type f -name *.proto); do
	protoc \




    "$p"
done
