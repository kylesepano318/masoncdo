"""Package a staging tree with Unix ZIP metadata and portable path separators."""
import sys
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

stage = Path(sys.argv[1]).resolve(strict=True)
archive = Path(sys.argv[2]).resolve()
if archive.is_relative_to(stage):
    raise SystemExit("Archive must be outside the staging directory.")
with ZipFile(archive, "x", compression=ZIP_DEFLATED) as output:
    for path in sorted(stage.rglob("*")):
        if path.is_symlink():
            raise SystemExit("Staging directory must not contain symlinks.")
        name = path.relative_to(stage).as_posix()
        directory = path.is_dir()
        info = ZipInfo(name + "/" if directory else name)
        info.create_system = 3
        info.external_attr = (0o40755 << 16) | 0x10 if directory else (0o100644 << 16)
        info.compress_type = ZIP_DEFLATED
        output.writestr(info, b"" if directory else path.read_bytes())
print(f"Linux-compatible package: {archive}")
