export async function compressImageFile(file, options = {}) {
  const {
    maxWidth = 1920,
    maxHeight = 1920,
    quality = 0.82,
    mimeType = 'image/jpeg',
  } = options;

  if (!file || !file.type || !file.type.startsWith('image/')) {
    return file;
  }

  if (file.type === 'image/svg+xml' || file.type === 'image/gif') {
    return file;
  }

  return new Promise((resolve) => {
    const objectUrl = URL.createObjectURL(file);
    const img = new Image();

    img.onload = () => {
      URL.revokeObjectURL(objectUrl);

      let width = img.naturalWidth || img.width;
      let height = img.naturalHeight || img.height;

      if (width > maxWidth || height > maxHeight) {
        const ratio = Math.min(maxWidth / width, maxHeight / height);
        width = Math.round(width * ratio);
        height = Math.round(height * ratio);
      }

      const canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;

      const ctx = canvas.getContext('2d');
      if (!ctx) {
        resolve(file);
        return;
      }

      if (mimeType === 'image/jpeg') {
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, width, height);
      }

      ctx.drawImage(img, 0, 0, width, height);

      canvas.toBlob(
        (blob) => {
          canvas.width = 0;
          canvas.height = 0;

          if (!blob) {
            resolve(file);
            return;
          }

          if (blob.size >= file.size && width === (img.naturalWidth || img.width)) {
            resolve(file);
            return;
          }

          const baseName = file.name ? file.name.replace(/\.[^/.]+$/, '') : `photo_${Date.now()}`;
          const ext = mimeType === 'image/jpeg' ? 'jpg' : 'png';
          try {
            const compressed = new File([blob], `${baseName}.${ext}`, {
              type: mimeType,
              lastModified: Date.now(),
            });
            resolve(compressed);
          } catch {
            blob.name = `${baseName}.${ext}`;
            resolve(blob);
          }
        },
        mimeType,
        quality
      );
    };

    img.onerror = () => {
      URL.revokeObjectURL(objectUrl);
      resolve(file);
    };

    img.src = objectUrl;
  });
}

export async function compressImageFiles(files, options = {}) {
  if (!files || !files.length) return [];
  const list = Array.from(files);
  const results = [];
  for (const f of list) {
    results.push(await compressImageFile(f, options));
  }
  return results;
}

