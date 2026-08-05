from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageFilter, ImageOps


ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "assets"
SOURCE = ASSETS / "brand" / "dongu-app-icon-original.png"


def smooth_symbol_mask(image: Image.Image) -> Image.Image:
    red, _green, _blue = image.convert("RGB").split()

    # The approved artwork has a dark emerald background and a bright lime emblem.
    # Red-channel separation retains the complete emblem without its background.
    mask = red.point(
        lambda value: 0
        if value <= 38
        else 255
        if value >= 105
        else round((value - 38) / 67 * 255)
    )
    return mask.filter(ImageFilter.MaxFilter(5)).filter(ImageFilter.GaussianBlur(1.2))


def prepare() -> None:
    if not SOURCE.exists():
        raise FileNotFoundError(f"Approved icon source is missing: {SOURCE}")

    source = Image.open(SOURCE).convert("RGB")
    square = ImageOps.fit(
        source,
        (1024, 1024),
        method=Image.Resampling.LANCZOS,
        centering=(0.5, 0.5),
    )

    # iOS and store artwork: opaque full-bleed PNG, no pre-rounded corners.
    square.save(ASSETS / "icon.png", optimize=True, quality=96)

    # Preserve the approved raster artwork exactly under Android launcher masks.
    square.save(ASSETS / "android-icon-background.png", optimize=True, quality=96)
    Image.new("RGBA", (1024, 1024), (0, 0, 0, 0)).save(
        ASSETS / "android-icon-foreground.png",
        optimize=True,
    )

    mask = smooth_symbol_mask(square)

    # Android themed and notification icons must be a single opaque silhouette.
    monochrome = Image.new("RGBA", (1024, 1024), (255, 255, 255, 0))
    monochrome.putalpha(mask)
    monochrome.save(ASSETS / "android-icon-monochrome.png", optimize=True)

    # Splash keeps the approved lime artwork but removes the square background.
    splash = square.convert("RGBA")
    splash.putalpha(mask)
    splash.save(ASSETS / "splash-icon.png", optimize=True)

    square.resize((96, 96), Image.Resampling.LANCZOS).save(
        ASSETS / "favicon.png",
        optimize=True,
        quality=95,
    )


if __name__ == "__main__":
    prepare()
