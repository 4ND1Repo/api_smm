<?php

namespace App\Helpers;

Class Motivation {

    public static $words = [
      ['author' => "Henry Ford", 'words' => "Whether you think you can, or you think you can’t – you’re right."],
      ['author' => "Carol Dweck, Mindset", 'words' => "Becoming is better than being."],
      ['author' => "Herman Melville, Moby Dick", 'words' => "I know not all that may be coming, but be it what it will, I’ll go to it laughing."],
      ['author' => "Charles Dickens, The Old Curiosity Shop", 'words' => "The sun himself is weak when he first rises, and gathers strength and courage as the day gets on."],
      ['author' => "Anne Frank, The Diary of a Young Girl", 'words' => "How wonderful it is that nobody need wait a single moment before starting to improve the world."],
      ['author' => "Rafiki, The Lion King", 'words' => "Oh yes, the past can hurt. But you can either run from it, or learn from it."],
      ['author' => "Abraham Lincoln", 'words' => "If I had nine hours to chop down a tree, I’d spend the first six sharpening my axe."],
      ['author' => "John Milton, Paradise Lost", 'words' => "The mind is its own place, and in itself can make a heaven of hell, a hell of heaven."],
      ['author' => "Stephen Hawking", 'words' => "Intelligence is the ability to adapt to change."],
      ['author' => "Stanley McChrystal", 'words' => "Leaders can let you fail and yet not let you be a failure."],
      ['author' => "Michael Scott, The Office", 'words' => "Would I rather be feared or loved? Easy. Both. I want people to be afraid of how much they love me."],
      ['author' => "Ayn Rand", 'words' => "The question isn’t who’s going to let me; it’s who is going to stop me?"],
      ['author' => "Babe Ruth, The Sandlot", 'words' => "Kid, there are heroes and there are legends. Heroes get remembered, but legends never die. Follow your heart, kid, and you’ll never go wrong."],
      ['author' => "Michael Jordan", 'words' => "Talent wins games, but teamwork and intelligence wins championships."],
      ['author' => "ane Austen, Mansfield Park", 'words' => "There will be little rubs and disappointments everywhere, and we are all apt to expect too much; but then, if one scheme of happiness fails, human nature turns to another; if the first calculation is wrong, we make a second better: we find comfort somewhere."]
    ];

    public static function get(){
      return self::$words[floor(rand(0,(count(self::$words)-1)))];
    }

}
