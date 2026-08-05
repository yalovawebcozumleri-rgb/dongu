from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont, ImageOps


ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "assets"
SOURCE = ASSETS / "brand" / "dongu-app-icon-original.png"
ICON_GREEN = (15, 78, 50, 255)
LAUNCHER_SYMBOL_SIZE = 560
BRAND_FONT_CANDIDATES = (
    Path("C:/Windows/Fonts/seguibl.ttf"),
    Path("C:/Windows/Fonts/segoeuib.ttf"),
    Path("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"),
)


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


def centered_symbol_mask(mask: Image.Image, size: int = LAUNCHER_SYMBOL_SIZE) -> Image.Image:
    bounds = mask.getbbox()
    if bounds is None:
        raise ValueError("Approved icon source does not contain a detectable emblem")

    symbol = mask.crop(bounds)
    symbol.thumbnail((size, size), Image.Resampling.LANCZOS)

    centered = Image.new("L", (1024, 1024), 0)
    centered.paste(
        symbol,
        ((centered.width - symbol.width) // 2, (centered.height - symbol.height) // 2),
    )
    return centered


def load_brand_font(size: int) -> ImageFont.FreeTypeFont:
    for candidate in BRAND_FONT_CANDIDATES:
        if candidate.exists():
            return ImageFont.truetype(str(candidate), size=size)

    raise FileNotFoundError("A bold brand font is required to render the splash wordmark")


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

    mask = smooth_symbol_mask(square)
    launcher_mask = centered_symbol_mask(mask)

    # Google-style launcher artwork: white outer surface and one centered brand mark.
    launcher_foreground = Image.new("RGBA", (1024, 1024), ICON_GREEN)
    launcher_foreground.putalpha(launcher_mask)
    launcher_foreground.save(ASSETS / "android-icon-foreground.png", optimize=True)

    white_background = Image.new("RGB", (1024, 1024), "white")
    white_background.save(ASSETS / "android-icon-background.png", optimize=True)

    # iOS and store artwork must be opaque; the OS applies its own launcher mask.
    ios_icon = white_background.copy()
    ios_icon.paste(launcher_foreground, (0, 0), launcher_foreground)
    ios_icon.save(ASSETS / "icon.png", optimize=True, quality=96)

    # Android themed and notification icons must be a single opaque silhouette.
    monochrome = Image.new("RGBA", (1024, 1024), (255, 255, 255, 0))
    monochrome.putalpha(launcher_mask)
    monochrome.save(ASSETS / "android-icon-monochrome.png", optimize=True)

    # Native splash: white surface, centered emblem and the compact Döngü wordmark.
    splash = Image.new("RGBA", (1024, 1024), (255, 255, 255, 0))
    splash_symbol = mask.crop(mask.getbbox())
    splash_symbol.thumbnail((440, 440), Image.Resampling.LANCZOS)
    splash_mask = Image.new("L", splash.size, 0)
    splash_mask.paste(
        splash_symbol,
        ((splash.width - splash_symbol.width) // 2, 130),
    )
    splash_mark = Image.new("RGBA", splash.size, ICON_GREEN)
    splash_mark.putalpha(splash_mask)
    splash.alpha_composite(splash_mark)

    wordmark = "döngü."
    draw = ImageDraw.Draw(splash)
    font = load_brand_font(142)
    text_bounds = draw.textbbox((0, 0), wordmark, font=font)
    text_width = text_bounds[2] - text_bounds[0]
    draw.text(
        ((splash.width - text_width) // 2, 635),
        wordmark,
        font=font,
        fill=ICON_GREEN,
    )
    splash.save(ASSETS / "splash-brand.png", optimize=True)

    ios_icon.resize((96, 96), Image.Resampling.LANCZOS).save(
        ASSETS / "favicon.png",
        optimize=True,
        quality=95,
    )


if __name__ == "__main__":
    prepare()
